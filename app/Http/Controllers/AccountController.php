<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\AccountImportService;
use App\Services\AccountStatusService;
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
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->context->hasClient()) {
            return redirect()->route('clients.index')
                ->with('toast', ['type' => 'warning', 'message' => 'Selecciona un cliente primero.']);
        }

        $client = $this->context->client();

        $accounts = Account::forClient($client->id)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(fn($s) => $s->where('numero_cuenta', 'like', "%{$term}%")
                    ->orWhere('nombre', 'like', "%{$term}%")
                    ->orWhere('codigo_agrupador', 'like', "%{$term}%"));
            })
            ->when($request->boolean('solo_afectables'), fn($q) => $q->where('es_afectable', true))
            ->when($request->boolean('solo_activas'), fn($q) => $q->activeFor($client->id))
            ->when(
                ! $request->has('todas'),
                fn($q) => $q->clientOwned($client->id)
            )
            ->orderBy('numero_cuenta')
            ->paginate(50)
            ->withQueryString();

        $activeCount = Account::forClient($client->id)->activeFor($client->id)->count();
        $total       = Account::forClient($client->id)->count();

        $counts = [
            'total'      => $total,
            'afectables' => Account::forClient($client->id)->where('es_afectable', true)->count(),
            'auto'       => Account::forClient($client->id)->where('auto_generada', true)->count(),
            'inactivas'  => $total - $activeCount,
        ];

        return view('accounts.index', [
            'client'        => $client,
            'accounts'      => $accounts,
            'counts'        => $counts,
            'overlay'       => $this->status->overlayMap($client->id),
            'parentOptions' => $this->parentOptions($client->id),
            'hiddenColumns' => auth()->user()->pref('accounts_columns', []),
            'q'             => $request->string('q')->toString(),
        ]);
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
            'nombre'       => ['required', 'string', 'max:200'],
            'rfc_asociado' => ['nullable', 'string', 'max:13'],
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
        } else {
            $n = count($result['changed']);
            $msg = $n > 0 ? "{$n} cuenta(s) inactivada(s)." : 'No se inactivó ninguna cuenta.';
            if ($result['blocked']) {
                $msg .= ' Con facturas asociadas (no inactivadas): ' . implode(', ', $result['blocked']) . '.';
            }
        }

        return response()->json(['ok' => true, 'result' => $result, 'message' => $msg]);
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
            'parent_id'        => ['nullable', 'integer', 'exists:accounts,id'],
        ]);
    }

    private function parentOptions(int $clientId)
    {
        return Account::forClient($clientId)
            ->orderBy('numero_cuenta')
            ->get(['id', 'numero_cuenta', 'nombre', 'codigo_agrupador', 'naturaleza', 'nivel']);
    }
}
