<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\AccountImportService;
use App\Services\AccountStatusService;
use App\Services\SaldosInicialesImportService;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Chart of accounts management per client: view the catálogo, import the SAT
 * código agrupador catalog, create/edit accounts, and activate/inactivate them
 * (per client — see AccountStatusService).
 */
class AccountController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
        private readonly AccountImportService $importer,
        private readonly AccountStatusService $status,
        private readonly SaldosInicialesImportService $saldosImporter,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->context->hasClient()) {
            return redirect()->route('clients.index')
                ->with('toast', ['type' => 'warning', 'message' => 'Selecciona un cliente primero.']);
        }

        $client = $this->context->client();

        // Defaults apply when a param is absent (fresh load / sidebar nav /
        // "Limpiar"): Estado=activas, Ámbito=incluir catálogo global. Once the
        // user touches the filter bar every param is submitted, so explicit
        // choices (e.g. Estado=todas) always win.
        $tipo       = $request->input('tipo', '');
        $naturaleza = $request->input('naturaleza', '');
        $nivel      = $request->input('nivel', '');
        $estado     = $request->has('estado') ? $request->input('estado') : 'activas';
        $todas      = $request->has('todas') ? $request->boolean('todas') : true;

        $accounts = Account::forClient($client->id)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(fn($s) => $s->where('numero_cuenta', 'like', "%{$term}%")
                    ->orWhere('nombre', 'like', "%{$term}%")
                    ->orWhere('codigo_agrupador', 'like', "%{$term}%"));
            })
            ->when($tipo === 'afectables', fn($q) => $q->where('es_afectable', true))
            ->when($tipo === 'agrupadoras', fn($q) => $q->where('es_afectable', false))
            ->when($naturaleza !== '', fn($q) => $q->where('naturaleza', $naturaleza))
            ->when($nivel !== '' && $nivel !== null, fn($q) => $q->where('nivel', (int) $nivel))
            ->when($estado === 'activas', fn($q) => $q->activeFor($client->id))
            ->when($estado === 'inactivas', fn($q) => $q->inactiveFor($client->id))
            ->when(! $todas, fn($q) => $q->clientOwned($client->id))
            ->orderBy('sort_key')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('accounts.index', [
            'client'        => $client,
            'accounts'      => $accounts,
            'overlay'       => $this->status->overlayMap($client->id),
            'parentOptions' => $this->parentOptions($client->id),
            'hiddenColumns' => auth()->user()->pref('accounts_columns', []),
            'q'             => $request->string('q')->toString(),
            'filters'       => [
                'tipo'       => $tipo,
                'naturaleza' => $naturaleza,
                'nivel'      => $nivel,
                'estado'     => $estado,
                'todas'      => $todas,
            ],
            'perPage'       => $this->perPage($request),
        ]);
    }

    /** Rows per page: one of 25/100/200/500, default 25. */
    private function perPage(Request $request): int
    {
        $allowed = [25, 100, 200, 500];
        $requested = (int) $request->integer('per_page', 25);

        return in_array($requested, $allowed, true) ? $requested : 25;
    }

    public function import(Request $request): JsonResponse
    {
        if (! $this->context->hasClient()) {
            return response()->json(['message' => 'Selecciona un cliente primero.'], 422);
        }

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $client = $this->context->client();
        $path = $request->file('archivo')->getRealPath();

        try {
            $summary = $this->importer->importFromFile($path, $client);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'      => true,
            'summary' => $summary,
            'message' => "Catálogo importado: {$summary['imported']} nuevas, {$summary['updated']} actualizadas.",
        ]);
    }

    /**
     * Step 1 of the saldos-iniciales import: parse the uploaded workbook and
     * return a preview the user checks/unchecks before committing. Nothing is
     * written here.
     */
    public function importSaldosPreview(Request $request): JsonResponse
    {
        if (! $this->context->hasClient()) {
            return response()->json(['message' => 'Selecciona un cliente primero.'], 422);
        }

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        try {
            $preview = $this->saldosImporter->parse(
                $request->file('archivo')->getRealPath(),
                $this->context->client(),
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'rows' => $preview]);
    }

    /** Step 2: apply the rows the user selected in the preview. */
    public function importSaldosCommit(Request $request): JsonResponse
    {
        if (! $this->context->hasClient()) {
            return response()->json(['message' => 'Selecciona un cliente primero.'], 422);
        }

        $data = $request->validate([
            'rows'               => ['required', 'array', 'min:1'],
            'rows.*.codigo'      => ['required', 'string', 'max:50'],
            'rows.*.descripcion' => ['nullable', 'string', 'max:255'],
            'rows.*.rfc'         => ['nullable', 'string', 'max:13'],
            'rows.*.saldo'       => ['nullable', 'numeric'],
            'rows.*.action'      => ['required', 'in:create,update,override'],
        ]);

        try {
            $summary = $this->saldosImporter->commit($this->context->client(), $data['rows']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $msg = "Saldos importados: {$summary['imported']} nuevas, {$summary['updated']} actualizadas"
             . ($summary['skipped'] ? ", {$summary['skipped']} omitidas." : '.');

        return response()->json(['ok' => true, 'summary' => $summary, 'message' => $msg]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateAccount($request);
        $client = $this->context->client();
        $data['client_id'] = $client->id;      // GlobalCatalogController sets null instead
        $data['activo'] = true;
        $account = Account::create($data);

        return response()->json(['ok' => true, 'message' => 'Cuenta creada.', 'id' => $account->id]);
    }

    /** Edit basic info (name + RFC) — client-owned accounts only, nothing that affects posting. */
    public function update(Request $request, Account $account): JsonResponse
    {
        if (! $this->context->hasClient()) {
            return response()->json(['message' => 'Selecciona un cliente primero.'], 422);
        }
        $clientId = $this->context->client()->id;

        if ($account->client_id !== $clientId) {
            return response()->json(['message' => 'Solo puedes editar las cuentas propias del cliente.'], 403);
        }

        $data = $request->validate([
            'nombre'        => ['required', 'string', 'max:200'],
            'rfc_asociado'  => ['nullable', 'string', 'max:13'],
            'saldo_inicial' => ['nullable', 'numeric'],
        ]);
        $account->update($data);

        return response()->json(['ok' => true, 'message' => 'Cuenta actualizada.']);
    }

    /** Activate / inactivate for the active client (overlay for global rows). */
    public function toggle(Request $request, Account $account): JsonResponse
    {
        if (! $this->context->hasClient()) {
            return response()->json(['message' => 'Selecciona un cliente primero.'], 422);
        }
        $clientId = $this->context->client()->id;

        if ($account->client_id !== null && $account->client_id !== $clientId) {
            return response()->json(['message' => 'La cuenta no pertenece a este cliente.'], 403);
        }

        $activo = $request->boolean('activo');
        $result = $this->status->toggle($account, $activo, $clientId);

        if ($activo) {
            $msg = "Cuenta {$account->numero_cuenta} activada.";
        } elseif (! empty($result['aborted'])) {
            // Whole branch left active because a node in it carries movimientos.
            $msg = 'No se inactivó ' . $account->numero_cuenta . ': '
                 . implode(', ', $result['blocked']) . ' tiene(n) movimientos registrados.';
        } else {
            $n = count($result['changed']);
            $msg = $n > 0 ? "{$n} cuenta(s) inactivada(s)." : 'No se inactivó ninguna cuenta.';
        }

        return response()->json(['ok' => true, 'result' => $result, 'message' => $msg]);
    }

    /**
     * Bulk activate / inactivate the selected accounts (current page selection).
     * Each account runs through the same per-client toggle rules; results are
     * aggregated. Inactivate is all-or-nothing per branch (see AccountStatusService),
     * so a father whose branch carries movimientos comes back under `blocked`.
     */
    public function bulkToggle(Request $request): JsonResponse
    {
        if (! $this->context->hasClient()) {
            return response()->json(['message' => 'Selecciona un cliente primero.'], 422);
        }
        $clientId = $this->context->client()->id;

        $data = $request->validate([
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
            'activo' => ['required', 'boolean'],
        ]);
        $activo = (bool) $data['activo'];

        $accounts = Account::forClient($clientId)->whereIn('id', $data['ids'])->get();

        $changed = [];
        $blocked = [];

        foreach ($accounts as $account) {
            // Ownership guard: global rows or this client's own accounts only.
            if ($account->client_id !== null && $account->client_id !== $clientId) {
                continue;
            }
            $res = $this->status->toggle($account, $activo, $clientId);
            $changed = array_merge($changed, $res['changed']);
            $blocked = array_merge($blocked, $res['blocked']);
        }

        $changed = array_values(array_unique($changed));
        $blocked = array_values(array_unique($blocked));

        $verb = $activo ? 'activada(s)' : 'inactivada(s)';
        $msg  = count($changed) > 0
            ? count($changed) . " cuenta(s) {$verb}."
            : 'No se modificó ninguna cuenta.';
        if ($blocked) {
            $msg .= ' Con movimientos (omitidas): ' . implode(', ', $blocked) . '.';
        }

        return response()->json([
            'ok'      => true,
            'changed' => $changed,
            'blocked' => $blocked,
            'message' => $msg,
        ]);
    }

    /** Suggest the next child code + inherited attributes for the create modal. */
    public function nextCode(Request $request): JsonResponse
    {
        if (! $this->context->hasClient()) {
            return response()->json(['message' => 'Selecciona un cliente primero.'], 422);
        }
        $clientId = $this->context->client()->id;

        $parent = Account::where('id', $request->integer('parent_id'))
            ->where(fn ($q) => $q->whereNull('client_id')->orWhere('client_id', $clientId))
            ->first();

        if (! $parent) {
            return response()->json(['message' => 'Cuenta padre no encontrada.'], 404);
        }

        $max = 0;
        $children = Account::where('parent_id', $parent->id)
            ->where(fn ($q) => $q->whereNull('client_id')->orWhere('client_id', $clientId))
            ->pluck('numero_cuenta');
        foreach ($children as $num) {
            if (preg_match('/\.(\d+)$/', (string) $num, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        $suffix = str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);

        return response()->json([
            'numero_cuenta'    => $parent->numero_cuenta . '.' . $suffix,
            'codigo_agrupador' => $parent->codigo_agrupador,
            'naturaleza'       => $parent->naturaleza,
            'nivel'            => min((int) ($parent->nivel ?? 1) + 1, 6),
            'tipo'             => 'Afectable',
        ]);
    }

    private function validateAccount(Request $request): array
    {
        return $request->validate([
            'numero_cuenta'    => ['required', 'string', 'max:50'],
            'nombre'           => ['required', 'string', 'max:200'],
            'codigo_agrupador' => ['required', 'string', 'max:20'],
            'naturaleza'       => ['required', 'in:D,A'],
            'nivel'            => ['required', 'integer', 'min:1', 'max:6'],
            'tipo'             => ['nullable', 'in:Afectable,Agrupador'],
            'rfc_asociado'     => ['nullable', 'string', 'max:13'],
            'es_afectable'     => ['boolean'],
            'saldo_inicial'    => ['nullable', 'numeric'],
            'parent_id'        => ['nullable', 'integer', 'exists:accounts,id'],
        ]);
    }

    /** Only agrupador (non-afectable) accounts can be a parent in the create modal. */
    private function parentOptions(int $clientId)
    {
        return Account::forClient($clientId)
            ->where('es_afectable', false)
            ->orderBy('sort_key')
            ->get(['id', 'numero_cuenta', 'nombre', 'codigo_agrupador', 'naturaleza', 'nivel']);
    }
}
