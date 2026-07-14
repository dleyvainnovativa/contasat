<?php

namespace App\Services;

use App\Models\BankProfile;
use RuntimeException;

/**
 * Extracts a bank statement PDF into structured movements.
 *
 * Pipeline:
 *   1. Pull raw text from the PDF (smalot/pdfparser — pure PHP, works on
 *      Hostinger shared hosting with no system binaries).
 *   2. Detect the bank profile from the text (keyword match) to tune the prompt.
 *   3. Ask the model for a strict JSON structure: statement header + movements.
 *   4. Normalize and run the balance-consistency gate.
 *
 * The extractor NEVER decides a statement is usable on its own — it returns the
 * data plus a `balance_cuadra` flag. The caller (job) persists it as "revision"
 * when the balance doesn't reconcile, keeping bad extractions out of matching.
 */
class BankStatementExtractor
{
    public function __construct(
        private readonly OpenAiClient $ai,
    ) {}

    /**
     * @return array{
     *   header: array,
     *   movements: array<int, array>,
     *   balance_cuadra: bool,
     *   profile: ?string
     * }
     */
    public function extract(string $pdfAbsolutePath): array
    {
        $text = $this->extractText($pdfAbsolutePath);

        if (mb_strlen(trim($text)) < 50) {
            throw new RuntimeException(
                'No se pudo leer texto del PDF. ¿Es un estado de cuenta escaneado (imagen)? '
                    . 'Esos requieren OCR y no están soportados en esta fase.'
            );
        }

        $profile = $this->detectProfile($text);
        $text = $this->clampText($text);

        $data = $this->ai->extractJson(
            $this->systemPrompt($profile),
            $this->userPrompt($text)
        );

        $header    = $this->normalizeHeader($data);
        $movements = $this->normalizeMovements($data['movimientos'] ?? []);

        // Balance-consistency gate: recompute totals from movements and compare
        // to the declared opening/closing balance.
        $balanceOk = $this->checkBalance($header, $movements);

        return [
            'header'         => $header,
            'movements'      => $movements,
            'balance_cuadra' => $balanceOk,
            'profile'        => $profile?->clave,
        ];
    }

    /**
     * Extract text from the statement PDF using Poppler's pdftotext (via
     * spatie/pdf-to-text), with the `layout` flag to preserve column alignment.
     *
     * Why not smalot/pdfparser: it builds PCRE patterns from the PDF's content
     * stream, and on statements containing inline images (the `BI`/`ID`/`EI`
     * operators — Santander embeds them) it tries to compile the raw image bytes
     * into a regex and dies with "regular expression is too large". pdftotext is
     * a native binary that doesn't have this failure mode, and `layout` keeps the
     * amount columns aligned in the output, which makes the AI's job easier.
     */
    private function extractText(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException('Archivo PDF no encontrado.');
        }

        try {
            return \Spatie\PdfToText\Pdf::getText(
                $path,
                config('services.pdftotext.path'),  // explicit path; null would search PATH
                ['layout'],
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'No se pudo leer el PDF. Verifica que sea un estado de cuenta en PDF con texto '
                    . '(no una imagen escaneada).',
                previous: $e,
            );
        }
    }

    /** Pick the bank profile whose detection keywords appear in the text. */
    private function detectProfile(string $text): ?BankProfile
    {
        $haystack = mb_strtolower($text);

        foreach (BankProfile::where('activo', true)->get() as $profile) {
            foreach ($profile->detection_keywords ?? [] as $kw) {
                if ($kw && str_contains($haystack, mb_strtolower($kw))) {
                    return $profile;
                }
            }
        }

        return null;
    }

    /** Keep the input within a safe size for the model. */
    private function clampText(string $text): string
    {
        $max = config('openai.max_input_chars');

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }

    private function systemPrompt(?BankProfile $profile): string
    {
        $base = <<<'PROMPT'
        Eres un extractor de datos de estados de cuenta bancarios mexicanos.
        Recibes el TEXTO plano de un estado de cuenta y devuelves ÚNICAMENTE un
        objeto JSON con esta estructura exacta:

        {
          "banco": "nombre del banco o null",
          "numero_cuenta": "número de cuenta enmascarado o null",
          "moneda": "MXN por defecto",
          "fecha_inicio": "YYYY-MM-DD o null",
          "fecha_fin": "YYYY-MM-DD o null",
          "saldo_inicial": número o null,
          "saldo_final": número o null,
          "total_cargos": número o null,
          "total_depositos": número o null,
          "movimientos": [
            {
              "fecha": "YYYY-MM-DD",
              "descripcion": "texto del concepto",
              "referencia": "referencia o null",
              "cargo": número (0 si no aplica),
              "deposito": número (0 si no aplica),
              "saldo": número o null
            }
          ]
        }

        Reglas:
        - Un movimiento tiene cargo O depósito, nunca ambos. El otro es 0.
        - Los cargos son retiros/egresos; los depósitos son abonos/ingresos.
        - Usa punto decimal, sin separadores de miles, sin símbolo de moneda.
        - Convierte todas las fechas a formato YYYY-MM-DD.
        - Incluye TODOS los movimientos, en el orden en que aparecen.
        - Si un dato no está presente, usa null (o 0 para cargo/deposito).
        - No inventes movimientos ni saldos. Devuelve sólo lo que está en el texto.
        - Responde SÓLO con el JSON, sin explicaciones ni markdown.
        PROMPT;

        if ($profile && $profile->hints) {
            $base .= "\n\nPistas específicas para {$profile->banco}:\n{$profile->hints}";
            if ($profile->formato_fecha) {
                $base .= "\nFormato de fecha típico: {$profile->formato_fecha}.";
            }
        }

        return $base;
    }

    private function userPrompt(string $text): string
    {
        return "TEXTO DEL ESTADO DE CUENTA:\n\n{$text}";
    }

    private function normalizeHeader(array $data): array
    {
        return [
            'banco'           => $this->str($data['banco'] ?? null),
            'numero_cuenta'   => $this->str($data['numero_cuenta'] ?? null),
            'moneda'          => $this->str($data['moneda'] ?? null) ?: 'MXN',
            'fecha_inicio'    => $this->date($data['fecha_inicio'] ?? null),
            'fecha_fin'       => $this->date($data['fecha_fin'] ?? null),
            'saldo_inicial'   => $this->num($data['saldo_inicial'] ?? null),
            'saldo_final'     => $this->num($data['saldo_final'] ?? null),
            'total_cargos'    => $this->num($data['total_cargos'] ?? null),
            'total_depositos' => $this->num($data['total_depositos'] ?? null),
        ];
    }

    /** @return array<int, array> */
    private function normalizeMovements(array $raw): array
    {
        $movements = [];

        foreach ($raw as $m) {
            $fecha = $this->date($m['fecha'] ?? null);
            if (! $fecha) {
                continue; // a movement with no usable date is unusable
            }

            $movements[] = [
                'fecha'       => $fecha,
                'descripcion' => $this->str($m['descripcion'] ?? null) ?: '(sin concepto)',
                'referencia'  => $this->str($m['referencia'] ?? null),
                'cargo'       => $this->num($m['cargo'] ?? 0) ?? 0.0,
                'deposito'    => $this->num($m['deposito'] ?? 0) ?? 0.0,
                'saldo'       => $this->num($m['saldo'] ?? null),
            ];
        }

        return $movements;
    }

    /**
     * The balance-consistency gate.
     *
     * If we have opening and closing balances, verify:
     *   saldo_inicial + sum(depositos) - sum(cargos) ≈ saldo_final
     * within a small tolerance (rounding). Returns false when it can't be
     * verified OR when it doesn't reconcile — either way the statement needs a
     * human look before matching.
     */
    private function checkBalance(array $header, array $movements): bool
    {
        $inicial = $header['saldo_inicial'];
        $final   = $header['saldo_final'];

        if ($inicial === null || $final === null || empty($movements)) {
            return false;
        }

        $cargos    = array_sum(array_column($movements, 'cargo'));
        $depositos = array_sum(array_column($movements, 'deposito'));

        $computed = $inicial + $depositos - $cargos;

        // Tolerance: one cent per movement accumulates rounding; cap at a peso.
        $tolerance = max(0.01, min(1.00, count($movements) * 0.01));

        return abs($computed - $final) <= $tolerance;
    }

    // --- small coercion helpers ------------------------------------------

    private function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' || strtolower($s) === 'null' ? null : $s;
    }

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return round((float) $v, 2);
        }
        // Strip currency symbols / thousands separators if the model slipped.
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $v);

        return $clean === '' ? null : round((float) $clean, 2);
    }

    private function date(mixed $v): ?string
    {
        $s = $this->str($v);
        if (! $s) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($s))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}
