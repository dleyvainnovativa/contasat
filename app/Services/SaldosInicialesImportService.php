<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Imports a client's "Saldos Iniciales" workbook into their chart of accounts.
 *
 * File format (4 columns, header row):
 *   Código agrupador | Descripción de la cuenta | RFC | Saldo Inicial
 *
 * Each row becomes a CHILD subaccount of the account named in "Código agrupador"
 * (resolved by numero_cuenta, global or client-owned). The child inherits the
 * parent's naturaleza / nivel / agrupador, takes the row's Descripción as its
 * name, and stores the Saldo Inicial. es_afectable = true, auto_generada = false.
 *
 * RFC de-duplication: if a row carries an RFC and a child already exists under
 * the same parent with that RFC — including subaccounts previously minted from
 * invoices (CounterpartyAccountService) — it's treated as the SAME account:
 *   - existing saldo is 0/empty  -> set it (status "update")
 *   - existing saldo is non-zero -> conflict; the user chooses override / ignore
 * Rows without an RFC can't be de-duplicated, so they always create a new child.
 *
 * Two steps, stateless (no file is persisted): parse() returns a preview the
 * user checks/unchecks; commit() applies exactly the rows the browser sends back,
 * re-resolving everything server-side.
 */
class SaldosInicialesImportService
{
    /**
     * Parse the file into preview rows for the confirm screen.
     *
     * @return array<int, array<string,mixed>>
     */
    public function parse(string $absolutePath, Client $client): array
    {
        $raw = $this->readRows($absolutePath);
        if (empty($raw)) {
            throw new RuntimeException('El archivo no contiene filas legibles.');
        }

        // Running per-parent suffix counters so the proposed numbers in the
        // preview don't collide with each other within the same file.
        $nextByParent = [];
        $preview = [];

        foreach ($raw as $i => $row) {
            $codigo = $row['codigo'];
            $rfc    = $row['rfc'];
            $parent = $this->resolveParent($codigo, $client->id);

            $entry = [
                'line'        => $i + 2,            // human row number (after header)
                'codigo'      => $codigo,
                'descripcion' => $row['descripcion'],
                'rfc'         => $rfc,
                'saldo'       => $row['saldo'],
                'parent_nombre' => $parent?->nombre,
                'status'      => 'new',
                'importable'  => true,
                'default_checked' => true,
                'existing_saldo'  => null,
                'proposed_numero' => null,
                'note'        => null,
            ];

            if (! $parent) {
                $preview[] = array_merge($entry, [
                    'status'     => 'error',
                    'importable' => false,
                    'default_checked' => false,
                    'note'       => "No existe la cuenta padre {$codigo} en el catálogo.",
                ]);
                continue;
            }

            // RFC match under this parent?
            $existing = $rfc ? $this->existingByRfc($parent->id, $client->id, $rfc) : null;

            if ($existing) {
                $hasSaldo = (float) $existing->saldo_inicial != 0.0;
                $preview[] = array_merge($entry, [
                    'status'          => $hasSaldo ? 'conflict' : 'update',
                    'existing_numero' => $existing->numero_cuenta,
                    'existing_saldo'  => (float) $existing->saldo_inicial,
                    'default_checked' => ! $hasSaldo,  // conflict defaults to ignore
                    'note'            => $hasSaldo
                        ? "Ya existe {$existing->numero_cuenta} con saldo. Marca para sobrescribir."
                        : "Coincide con {$existing->numero_cuenta}; se asignará el saldo.",
                ]);
                continue;
            }

            // New child — propose the next suffix (display only; commit recomputes).
            if (! isset($nextByParent[$parent->id])) {
                $nextByParent[$parent->id] = $this->maxSuffix($parent->id, $client->id);
            }
            $nextByParent[$parent->id]++;
            $preview[] = array_merge($entry, [
                'proposed_numero' => $parent->numero_cuenta . '.' . $nextByParent[$parent->id],
            ]);
        }

        return $preview;
    }

    /**
     * Apply the selected rows. Each row carries codigo, descripcion, rfc, saldo
     * and an action ('create' | 'update' | 'override'). Everything is re-resolved
     * here — the browser's account identities are not trusted.
     *
     * @param  array<int, array<string,mixed>>  $rows
     * @return array{imported:int, updated:int, skipped:int, errors:array<int,string>}
     */
    public function commit(Client $client, array $rows): array
    {
        $summary = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $counters = [];   // parent_id => last suffix used this commit

        DB::transaction(function () use ($client, $rows, &$summary, &$counters) {
            foreach ($rows as $row) {
                $codigo      = $this->cleanCode((string) ($row['codigo'] ?? ''));
                $descripcion = trim((string) ($row['descripcion'] ?? ''));
                $rfc         = $this->cleanRfc($row['rfc'] ?? null);
                $saldo       = $this->toFloat($row['saldo'] ?? 0);
                $action      = $row['action'] ?? 'create';

                $parent = $this->resolveParent($codigo, $client->id);
                if (! $parent) {
                    $summary['skipped']++;
                    $summary['errors'][] = "Cuenta padre {$codigo} no encontrada.";
                    continue;
                }

                // RFC match => update existing (honour override on conflict).
                if ($rfc) {
                    $existing = $this->existingByRfc($parent->id, $client->id, $rfc);
                    if ($existing) {
                        $hasSaldo = (float) $existing->saldo_inicial != 0.0;
                        if ($hasSaldo && $action !== 'override') {
                            $summary['skipped']++;      // ignore
                            continue;
                        }
                        $existing->update(['saldo_inicial' => $saldo]); // saldo only
                        $summary['updated']++;
                        continue;
                    }
                }

                // New child under the parent.
                if (! isset($counters[$parent->id])) {
                    $counters[$parent->id] = $this->maxSuffix($parent->id, $client->id);
                }
                $counters[$parent->id]++;
                $numero = $parent->numero_cuenta . '.' . $counters[$parent->id];

                Account::create([
                    'client_id'        => $client->id,
                    'parent_id'        => $parent->id,
                    'codigo_agrupador' => $parent->codigo_agrupador,
                    'numero_cuenta'    => $numero,
                    'nombre'           => $descripcion !== '' ? mb_substr($descripcion, 0, 200) : $numero,
                    'rfc_asociado'     => $rfc,
                    'nivel'            => min((int) ($parent->nivel ?? 1) + 1, 6),
                    'naturaleza'       => $parent->naturaleza,
                    'tipo'             => 'Afectable',
                    'es_afectable'     => true,
                    'auto_generada'    => false,
                    'activo'           => true,
                    'saldo_inicial'    => $saldo,
                ]);
                $summary['imported']++;
            }
        });

        return $summary;
    }

    /** Resolve the parent account named by a código agrupador (global or client). */
    private function resolveParent(string $codigo, int $clientId): ?Account
    {
        return Account::forClient($clientId)
            ->where('numero_cuenta', $codigo)
            ->orderByRaw('client_id IS NULL')   // prefer a client-owned match over global
            ->first();
    }

    private function existingByRfc(int $parentId, int $clientId, string $rfc): ?Account
    {
        return Account::clientOwned($clientId)
            ->where('parent_id', $parentId)
            ->where('rfc_asociado', $rfc)
            ->first();
    }

    /** Highest numeric suffix already used by children of $parentId (global + client). */
    private function maxSuffix(int $parentId, int $clientId): int
    {
        $max = 0;
        $codes = Account::where('parent_id', $parentId)
            ->where(fn ($q) => $q->whereNull('client_id')->orWhere('client_id', $clientId))
            ->pluck('numero_cuenta');

        foreach ($codes as $code) {
            if (preg_match('/\.(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }

    /**
     * Read the 4-column file into normalized rows. Reads formatted values so
     * numeric agrupador codes keep their dotted form.
     *
     * @return array<int, array{codigo:string, descripcion:string, rfc:?string, saldo:float}>
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

        // formatData = true keeps codes like 105.01 as text instead of floats.
        $cells = $spreadsheet->getActiveSheet()->toArray(null, true, true);
        $out = [];

        foreach ($cells as $i => $r) {
            $codigo = $this->cleanCode(isset($r[0]) ? (string) $r[0] : '');
            $desc   = isset($r[1]) ? trim((string) $r[1]) : '';
            $rfc    = $this->cleanRfc($r[2] ?? null);
            $saldo  = $this->toFloat($r[3] ?? 0);

            // Skip the header row.
            if ($i === 0 && (mb_stripos((string) ($r[0] ?? ''), 'agrupador') !== false
                || mb_stripos((string) ($r[1] ?? ''), 'descrip') !== false)) {
                continue;
            }

            // A data row needs a dotted/numeric código.
            if ($codigo === '' || ! preg_match('/^\d+(\.\d+)*$/', $codigo)) {
                continue;
            }

            $out[] = ['codigo' => $codigo, 'descripcion' => $desc, 'rfc' => $rfc, 'saldo' => $saldo];
        }

        return $out;
    }

    private function cleanCode(string $codigo): string
    {
        // Keep digits and dots; trim a trailing dot if any.
        $c = preg_replace('/[^\d.]/', '', trim($codigo)) ?? '';
        return rtrim($c, '.');
    }

    private function cleanRfc(mixed $rfc): ?string
    {
        $rfc = strtoupper(trim((string) ($rfc ?? '')));
        return $rfc !== '' ? $rfc : null;
    }

    private function toFloat(mixed $v): float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        // Strip currency symbols, thousands separators, spaces.
        $clean = preg_replace('/[^\d.\-]/', '', str_replace(',', '', (string) $v)) ?? '';
        return $clean === '' ? 0.0 : (float) $clean;
    }
}
