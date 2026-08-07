<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Imports the SAT "Código Agrupador" catalog into a client's chart of accounts.
 *
 * The SAT file (xlsx or csv) has three columns:
 *   Nivel | Código agrupador | Nombre de la cuenta y/o subcuenta
 *
 * Structure rules derived from the format:
 *   - The código agrupador's dot-depth gives the hierarchy: 100 -> 100.01 -> 101
 *     nests by matching a row to its longest existing prefix.
 *   - `Nivel` distinguishes postable accounts from grouping headers. Grouping
 *     rows (blank Nivel, or the top agrupador rows like 100 / 100.01) are NOT
 *     afectable — they're headers. Rows with a populated Nivel are real accounts
 *     that can receive postings, so es_afectable = true.
 *   - The código agrupador doubles as the account number (NumCta), per decision.
 *
 * Naturaleza (D/A) is inferred from the account's major group, following the
 * standard accounting equation: assets & expenses are debit-natured (D),
 * liabilities, equity & income are credit-natured (A).
 */
class AccountImportService
{
    /**
     * @return array{imported:int, updated:int, skipped:int}
     */
    public function importFromFile(string $absolutePath, ?\App\Models\Client $client = null): array
    {
        $rows = $this->readRows($absolutePath);

        if (empty($rows)) {
            throw new \RuntimeException('El archivo no contiene filas legibles.');
        }

        $clientId = $client?->id;   // null => global

        $summary = ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        $codeToId = [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($rows, $clientId, &$summary, &$codeToId) {
            foreach ($rows as $row) {
                $codigo = $this->cleanCode($row['codigo']);
                if ($codigo === '') {
                    $summary['skipped']++;
                    continue;
                }

                $nivel = $this->parseNivel($row['nivel'], $codigo);
                $afectable = $this->isAfectable($row['nivel'], $codigo);

                // Match on (client_id, numero_cuenta). For global, client_id is null;
                // updateOrCreate handles the null match correctly.
                $account = \App\Models\Account::updateOrCreate(
                    ['client_id' => $clientId, 'numero_cuenta' => $codigo],
                    [
                        'codigo_agrupador' => $codigo,
                        'nombre'           => trim($row['nombre']) ?: $codigo,
                        'nivel'            => $nivel,
                        'naturaleza'       => $this->naturaleza($codigo),
                        'es_afectable'     => $afectable,
                        'auto_generada'    => false,
                        'activo'           => true,
                    ],
                );

                $account->wasRecentlyCreated ? $summary['imported']++ : $summary['updated']++;
                $codeToId[$codigo] = $account->id;
            }

            foreach ($codeToId as $codigo => $id) {
                $parentCode = $this->parentCode($codigo, $codeToId);
                if ($parentCode !== null) {
                    \App\Models\Account::where('id', $id)->update(['parent_id' => $codeToId[$parentCode]]);
                }
            }
        });

        return $summary;
    }

    /**
     * Read the file (xlsx or csv) into normalized rows. Skips the header row and
     * anything that doesn't look like data.
     *
     * @return array<int, array{nivel:string, codigo:string, nombre:string}>
     */
    private function readRows(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Archivo no encontrado.');
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo leer el archivo: ' . $e->getMessage(), previous: $e);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $out = [];

        foreach ($sheet->toArray() as $i => $cells) {
            // Expect at least 3 columns. Tolerate extra.
            $nivel  = isset($cells[0]) ? trim((string) $cells[0]) : '';
            $codigo = isset($cells[1]) ? trim((string) $cells[1]) : '';
            $nombre = isset($cells[2]) ? trim((string) $cells[2]) : '';

            // Skip the header row (matches the SAT column titles).
            if ($i === 0 && (mb_stripos($codigo, 'código') !== false || mb_stripos($codigo, 'codigo') !== false || mb_stripos($nombre, 'nombre') !== false)) {
                continue;
            }

            // A row needs a código that looks like a SAT agrupador (digits + dots).
            if ($codigo === '' || ! preg_match('/^\d+(\.\d+)*$/', $codigo)) {
                continue;
            }

            $out[] = ['nivel' => $nivel, 'codigo' => $codigo, 'nombre' => $nombre];
        }

        return $out;
    }

    private function cleanCode(string $codigo): string
    {
        return preg_replace('/[^\d.]/', '', $codigo) ?? '';
    }

    /**
     * Nivel for the catálogo XML is the account's depth in the hierarchy, which
     * the código agrupador encodes structurally: 100 = level 1, 100.01 = level 2,
     * 100.01.001 = level 3.
     *
     * We derive it from dot-depth rather than the file's Nivel column, because
     * that column reflects SAT's own account-classification sense and can disagree
     * with the tree (e.g. 105.01 is marked Nivel 1 in the file but is structurally
     * a level-2 child of 105). The XML's Nivel attribute must match the hierarchy,
     * so structure wins.
     */
    private function parseNivel(string $nivelRaw, string $codigo): int
    {
        return substr_count($codigo, '.') + 1;
    }

    /**
     * A row is postable (afectable) when the file marks it with an explicit Nivel.
     * The SAT catalog leaves Nivel blank on pure grouping rows (the agrupador
     * headers like 100 "Activo" and 100.01 "Activo a corto plazo"), and populates
     * it on the real accounts (101 "Caja" with Nivel 1). We mirror that: explicit
     * Nivel => afectable; blank => header.
     */
    private function isAfectable(string $nivelRaw, string $codigo): bool
    {
        return trim($nivelRaw) !== '' && is_numeric(trim($nivelRaw));
    }

    /**
     * Naturaleza by major group (first path segment):
     *   1xx Activo -> D | 2xx Pasivo -> A | 3xx Capital -> A
     *   4xx Ingresos -> A | 5xx Costos -> D | 6xx Gastos -> D
     *   7xx/8xx resultado & orden -> default A
     */
    private function naturaleza(string $codigo): string
    {
        $first = (int) substr($codigo, 0, 1);

        return match ($first) {
            1, 5, 6 => 'D',
            2, 3, 4 => 'A',
            default => 'A',
        };
    }

    /**
     * The parent code is the longest strict prefix of $codigo that also exists in
     * the imported set. e.g. parent of "105.01" is "105"; parent of "101" is
     * whichever of "100.01"/"100" exists — but SAT codes nest by dots, so we trim
     * the last dot segment first, then fall back to major-group grouping.
     */
    private function parentCode(string $codigo, array $codeToId): ?string
    {
        // Dot hierarchy: 105.01.001 -> 105.01 -> 105
        if (str_contains($codigo, '.')) {
            $parent = substr($codigo, 0, strrpos($codigo, '.'));

            return isset($codeToId[$parent]) ? $parent : $this->groupParent($codigo, $codeToId);
        }

        return $this->groupParent($codigo, $codeToId);
    }

    /**
     * For a top-level numeric code like "101", its logical parent is the group
     * header "100.01" or "100" if present. We look for the nearest lower hundred
     * grouping row.
     */
    private function groupParent(string $codigo, array $codeToId): ?string
    {
        $base = (int) explode('.', $codigo)[0];
        if ($base <= 0) {
            return null;
        }

        // Nearest hundred (101 -> 100). Don't parent a header to itself.
        $hundred = (string) (intdiv($base, 100) * 100);
        if ($hundred !== $codigo && isset($codeToId[$hundred])) {
            return $hundred;
        }

        return null;
    }
}
