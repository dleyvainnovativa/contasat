<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Client;
use App\Services\AccountImportService;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Chart of accounts management per client: view the catálogo, import the SAT
 * código agrupador catalog, and see auto-generated counterparty subaccounts.
 */
class AccountController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
        private readonly AccountImportService $importer,
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
            ->when(
                ! $request->has('todas'),
                fn($q) => $q->clientOwned($client->id)
            )
            ->orderBy('numero_cuenta')
            ->paginate(50)
            ->withQueryString();

        $counts = [
            'total'      => Account::forClient($client->id)->count(),
            'afectables' => Account::forClient($client->id)->where('es_afectable', true)->count(),
            'auto'       => Account::forClient($client->id)->where('auto_generada', true)->count(),
        ];

        return view('accounts.index', [
            'client'   => $client,
            'accounts' => $accounts,
            'counts'   => $counts,
            'hiddenColumns' => auth()->user()->pref('accounts_columns', []),
            'q'        => $request->string('q')->toString(),
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
        $data = $request->validate([
            'numero_cuenta'    => ['required', 'string', 'max:50'],
            'nombre'           => ['required', 'string', 'max:200'],
            'codigo_agrupador' => ['required', 'string', 'max:20'],
            'naturaleza'       => ['required', 'in:D,A'],
            'nivel'            => ['required', 'integer', 'min:1', 'max:6'],
            'es_afectable'     => ['boolean'],
            'parent_id'        => ['nullable', 'integer', 'exists:accounts,id'],
        ]);
        $client = $this->context->client();
        $data['client_id'] = $client->id;      // GlobalCatalogController sets null instead
        $data['activo'] = true;
        $account = Account::create($data);
        return response()->json(['ok' => true, 'message' => 'Cuenta creada.', 'id' => $account->id]);
    }
}
