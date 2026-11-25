<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Imports the SAT "Código Agrupador" catalog into a chart of accounts (global
 * when $client is null, or a client's own otherwise).
 *
 * Two layouts are accepted, detected from the header row:
 *
 *   LEGACY (3 columns):
 *       Nivel | Código agrupador | Nombre de la cuenta y/o subcuenta
 *     es_afectable is guessed (blank Nivel = grouping header) and naturaleza is
 *     inferred from the major group.
 *
 *   RICH (CAT + CONTPAQ):
 *       Nivel | Código agrupador | Cuenta(Activa/No Activa) | Tipo(Afectable/
 *       Agrupador) | Naturaleza | Nombre de la cuenta | Nombre de subcuenta |
 *       Tipo(clasificación) | Cuenta de mayor | Nivel (NIF) | Rubro (NIF)
 *     Here the file is authoritative for es_afectable, tipo, naturaleza and
 *     activo, and carries the extra NIF/CONTPAQ descriptive columns.
 *
 * Shared rules:
 *   - The código agrupador doubles as numero_cuenta. Codes are matched to
 *     existing rows by NUMERIC value, so cosmetic format differences (100 vs
 *     100.00, 106.1 vs 106.10) reconcile to one row instead of duplicating.
 *   - Structural `nivel` is the dot-depth of the code (what the contabilidad
 *     electrónica XML needs), NOT the file's Nivel column.
 *   - Hierarchy is linked by code prefix (105.01 -> 105), unchanged.
 */
class AccountImportService
{
    /**
     * @return array{imported:int, updated:int, skipped:int}
     */
    public function importFromFile(string $absolutePath, ?Client $client = null): array
    {
        $parsed = $this->readRows($absolutePath);
        $rows   = $parsed['rows'];

        if (empty($rows)) {
            throw new RuntimeException('El archivo no contiene filas legibles.');
        }

        $clientId = $client?->id;   // null => global

        $summary  = ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        $codeToId = [];

        DB::transaction(function () use ($rows, $clientId, &$summary, &$codeToId) {
            // Existing rows in this scope, keyed by numeric value for format-safe matching.
            $existing = Account::query()
                ->when($clientId === null, fn ($q) => $q->whereNull('client_id'))
                ->when($clientId !== null, fn ($q) => $q->where('client_id', $clientId))
                ->get(['id', 'numero_cuenta'])
                ->keyBy(fn ($a) => $this->numKey($a->numero_cuenta));

            foreach ($rows as $row) {
                $codigo = $row['codigo'];
                if ($codigo === '') {
                    $summary['skipped']++;
                    continue;
                }

                $attrs = [
                    'client_id'        => $clientId,
                    'codigo_agrupador' => $codigo,
                    'numero_cuenta'    => $codigo,
                    'nombre'           => $row['nombre'] !== '' ? $row['nombre'] : $codigo,
                    'nombre_subcuenta' => $row['nombre_subcuenta'],
                    'nivel'            => substr_count($codigo, '.') + 1,   // structural depth
                    'naturaleza'       => $row['naturaleza'],
                    'tipo'             => $row['tipo'],
                    'clasificacion'    => $row['clasificacion'],
                    'cuenta_mayor'     => $row['cuenta_mayor'],
                    'nivel_nif'        => $row['nivel_nif'],
                    'rubro_nif'        => $row['rubro_nif'],
                    'es_afectable'     => $row['es_afectable'],
                    'activo'           => $row['activo'],
                    'auto_generada'    => false,
                ];

                $found = $existing->get($this->numKey($codigo));
                if ($found) {
                    Account::whereKey($found->id)->update($attrs);
                    $id = $found->id;
                    $summary['updated']++;
                } else {
                    $id = Account::create($attrs)->id;
                    $summary['imported']++;
                }

                $codeToId[$codigo] = $id;
            }

            // Link parents by code prefix (105.01 -> 105; 101 -> 100).
            foreach ($codeToId as $codigo => $id) {
                $parentCode = $this->parentCode($codigo, $codeToId);
                if ($parentCode !== null && $parentCode !== $codigo) {
                    Account::whereKey($id)->update(['parent_id' => $codeToId[$parentCode]]);
                }
            }
        });

        return $summary;
    }

    /**
     * Read and normalize the file into rows, resolving columns by header name and
     * by value (so the two "Tipo" columns and the Activa column are found reliably).
     *
     * @return array{format:string, rows:array<int,array<string,mixed>>}
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

        $sheet = $this->pickSheet($spreadsheet);
        // formatData = true so the numeric código column keeps its 0.00 form.
        $grid = $sheet->toArray(null, true, true);

        $headerRow = $this->findHeaderRow($grid);
        if ($headerRow === null) {
            throw new RuntimeException('No se encontró la columna "Código agrupador" en el archivo.');
        }

        $map = $this->mapColumns($grid, $headerRow);
        if (! isset($map['codigo'])) {
            throw new RuntimeException('No se encontró la columna de código agrupador.');
        }

        $rich = isset($map['nombre_subcuenta']) || isset($map['clasificacion']) || isset($map['activa']);
        $rows = [];

        foreach (array_slice($grid, $headerRow + 1) as $cells) {
            $codigo = $this->canonicalCode($this->cell($cells, $map['codigo'] ?? null));
            if ($codigo === '' || ! preg_match('/^\d+(\.\d+)*$/', $codigo)) {
                continue;
            }

            $nombre = trim($this->cell($cells, $map['nombre'] ?? null));

            if ($rich) {
                $tipoRaw = mb_strtolower(trim($this->cell($cells, $map['tipo'] ?? null)));
                $afectable = $tipoRaw === 'afectable';
                $rows[] = [
                    'codigo'           => $codigo,
                    'nombre'           => $nombre,
                    'nombre_subcuenta' => $this->nullable($this->cell($cells, $map['nombre_subcuenta'] ?? null)),
                    'naturaleza'       => $this->naturalezaFrom($this->cell($cells, $map['naturaleza'] ?? null), $codigo),
                    'tipo'             => $afectable ? 'Afectable' : 'Agrupador',
                    'clasificacion'    => $this->nullable($this->cell($cells, $map['clasificacion'] ?? null)),
                    'cuenta_mayor'     => $this->nullable($this->cell($cells, $map['cuenta_mayor'] ?? null)),
                    'nivel_nif'        => $this->nullable($this->cell($cells, $map['nivel_nif'] ?? null)),
                    'rubro_nif'        => $this->nullable($this->cell($cells, $map['rubro_nif'] ?? null)),
                    'es_afectable'     => $afectable,
                    'activo'           => mb_strtolower(trim($this->cell($cells, $map['activa'] ?? null))) === 'activa',
                ];
            } else {
                $nivelRaw = trim($this->cell($cells, $map['nivel'] ?? null));
                $rows[] = [
                    'codigo'           => $codigo,
                    'nombre'           => $nombre,
                    'nombre_subcuenta' => null,
                    'naturaleza'       => $this->naturalezaFromCode($codigo),
                    'tipo'             => ($nivelRaw !== '' && is_numeric($nivelRaw)) ? 'Afectable' : 'Agrupador',
                    'clasificacion'    => null,
                    'cuenta_mayor'     => null,
                    'nivel_nif'        => null,
                    'rubro_nif'        => null,
                    'es_afectable'     => $nivelRaw !== '' && is_numeric($nivelRaw),
                    'activo'           => true,
                ];
            }
        }

        return ['format' => $rich ? 'rich' : 'legacy', 'rows' => $rows];
    }

    /** Prefer a sheet whose header contains "código agrupador"; else the active one. */
    private function pickSheet($spreadsheet)
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $grid = $sheet->toArray(null, true, true);
            if ($this->findHeaderRow($grid) !== null) {
                return $sheet;
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    /** Index of the row that holds the column titles (contains "código agrupador"). */
    private function findHeaderRow(array $grid): ?int
    {
        foreach (array_slice($grid, 0, 10, true) as $i => $cells) {
            foreach ($cells as $v) {
                if (mb_stripos($this->norm($v), 'codigo agrupador') !== false) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Resolve column indices from the header row plus value sampling for the
     * ambiguous columns (two "Tipo" headers, and the Activa column labelled "Cuenta").
     *
     * @return array<string,int>
     */
    private function mapColumns(array $grid, int $headerRow): array
    {
        $header = $grid[$headerRow];
        $map = [];
        $tipoCols = [];

        foreach ($header as $idx => $raw) {
            $h = $this->norm($raw);
            if ($h === '') {
                continue;
            }
            if (str_contains($h, 'codigo agrupador'))              $map['codigo'] = $idx;
            elseif (str_contains($h, 'nombre de la cuenta'))       $map['nombre'] = $idx;
            elseif (str_contains($h, 'nombre de subcuenta'))       $map['nombre_subcuenta'] = $idx;
            elseif (str_contains($h, 'naturaleza'))                $map['naturaleza'] = $idx;
            elseif (str_contains($h, 'cuenta de mayor'))           $map['cuenta_mayor'] = $idx;
            elseif (str_contains($h, 'nivel') && str_contains($h, 'nif')) $map['nivel_nif'] = $idx;
            elseif (str_contains($h, 'rubro'))                     $map['rubro_nif'] = $idx;
            elseif ($h === 'nivel')                                $map['nivel'] = $idx;
            elseif ($h === 'tipo')                                 $tipoCols[] = $idx;
        }

        // Sample values to split the two "Tipo" columns and locate the Activa column.
        $sample = array_slice($grid, $headerRow + 1, 60);

        foreach ($tipoCols as $idx) {
            $vals = $this->sampleValues($sample, $idx);
            if ($vals && $this->allIn($vals, ['afectable', 'agrupador'])) {
                $map['tipo'] = $idx;           // Afectable / Agrupador
            } else {
                $map['clasificacion'] = $idx;  // "Activo deudora", etc.
            }
        }

        if (! isset($map['activa'])) {
            foreach (array_keys($header) as $idx) {
                $vals = $this->sampleValues($sample, $idx);
                if ($vals && $this->allIn($vals, ['activa', 'no activa'])) {
                    $map['activa'] = $idx;
                    break;
                }
            }
        }

        return $map;
    }

    /** Distinct lowercased non-empty values in a column across sample rows. */
    private function sampleValues(array $sample, int $idx): array
    {
        $out = [];
        foreach ($sample as $cells) {
            $v = mb_strtolower(trim($this->cell($cells, $idx)));
            if ($v !== '') {
                $out[$v] = true;
            }
        }

        return array_keys($out);
    }

    private function allIn(array $vals, array $allowed): bool
    {
        return count(array_diff($vals, $allowed)) === 0;
    }

    private function cell(array $cells, ?int $idx): string
    {
        if ($idx === null || ! array_key_exists($idx, $cells) || $cells[$idx] === null) {
            return '';
        }

        return (string) $cells[$idx];
    }

    private function nullable(string $v): ?string
    {
        $v = trim($v);

        return $v === '' ? null : mb_substr($v, 0, 160);
    }

    private function norm(mixed $v): string
    {
        $s = mb_strtolower(trim((string) ($v ?? '')));

        // Fold accents so "Código" matches "codigo", "Clasificación" etc.
        return strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    /**
     * Canonical código: integers keep no decimals, sub-accounts keep their
     * decimals. The file's 0.00 format yields e.g. "100.00" -> "100",
     * "101.01" -> "101.01", "106.10" -> "106.10".
     */
    private function canonicalCode(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Plain dotted integer/decimal from the formatted cell. The 0.00 format
        // yields 2-digit sub-codes; keep them verbatim ("106.10" stays "106.10"),
        // collapsing only the all-zero decimal ("100.00" -> "100").
        if (preg_match('/^\d+\.\d+$/', $raw)) {
            [$int, $dec] = explode('.', $raw, 2);

            return preg_match('/^0+$/', $dec) ? $int : "{$int}.{$dec}";
        }

        if (preg_match('/^\d+$/', $raw)) {
            return $raw;
        }

        // Anything else (already multi-level or noisy): strip to digits/dots.
        return rtrim(preg_replace('/[^\d.]/', '', $raw) ?? '', '.');
    }

    /** Numeric match key so 100 / 100.00 and 106.1 / 106.10 collapse to one row. */
    private function numKey(string $code): string
    {
        $code = trim($code);
        // Multi-level codes (counterparty subaccounts) match by exact string.
        if (substr_count($code, '.') > 1 || ! is_numeric($code)) {
            return 's:' . $code;
        }

        return 'n:' . number_format((float) $code, 2, '.', '');
    }

    private function naturalezaFrom(string $raw, string $codigo): string
    {
        $r = mb_strtolower(trim($raw));
        if (str_starts_with($r, 'd')) {
            return 'D';
        }
        if (str_starts_with($r, 'a')) {
            return 'A';
        }

        return $this->naturalezaFromCode($codigo);
    }

    /** Naturaleza by major group when the file doesn't state it. */
    private function naturalezaFromCode(string $codigo): string
    {
        return match ((int) substr($codigo, 0, 1)) {
            1, 5, 6 => 'D',
            default => 'A',
        };
    }

    /** Parent code: dot hierarchy first (105.01 -> 105), else nearest hundred (101 -> 100). */
    private function parentCode(string $codigo, array $codeToId): ?string
    {
        if (str_contains($codigo, '.')) {
            $parent = substr($codigo, 0, strrpos($codigo, '.'));

            return isset($codeToId[$parent]) ? $parent : $this->groupParent($codigo, $codeToId);
        }

        return $this->groupParent($codigo, $codeToId);
    }

    private function groupParent(string $codigo, array $codeToId): ?string
    {
        $base = (int) explode('.', $codigo)[0];
        if ($base <= 0) {
            return null;
        }
        $hundred = (string) (intdiv($base, 100) * 100);

        return ($hundred !== $codigo && isset($codeToId[$hundred])) ? $hundred : null;
    }
}
