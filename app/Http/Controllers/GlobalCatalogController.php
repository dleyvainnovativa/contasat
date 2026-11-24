<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\AccountImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The global SAT catalog — the ~700 código agrupador accounts shared by every
 * client (client_id null). Imported once here, not per client. Client-specific
 * counterparty subaccounts (105.01.###) are never shown or created here; they
 * live on the per-client accounts page.
 */
class GlobalCatalogController extends Controller
{
    public function __construct(
        private readonly AccountImportService $importer,
    ) {}

    public function index(Request $request): View
    {
        $accounts = Account::global()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(fn($s) => $s->where('numero_cuenta', 'like', "%{$term}%")
                    ->orWhere('nombre', 'like', "%{$term}%")
                    ->orWhere('codigo_agrupador', 'like', "%{$term}%"));
            })
            ->when($request->boolean('solo_afectables'), fn($q) => $q->where('es_afectable', true))
            ->orderBy('sort_key')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $counts = [
            'total'      => Account::global()->count(),
            'afectables' => Account::global()->where('es_afectable', true)->count(),
            'activas'    => Account::global()->where('activo', true)->count(),
            'inactivas'  => Account::global()->where('activo', false)->count(),
        ];

        return view('catalog.index', [
            'accounts' => $accounts,
            'counts'   => $counts,
            'parentOptions' => Account::global()
                ->where('es_afectable', false)
                ->orderBy('sort_key')
                ->get(['id', 'numero_cuenta', 'nombre', 'codigo_agrupador', 'naturaleza', 'nivel']),
            'hiddenColumns' => auth()->user()->pref('accounts_columns', []),
            'q'        => $request->string('q')->toString(),
            'perPage'  => $this->perPage($request),
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
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        try {
            // null client => global import
            $summary = $this->importer->importFromFile($request->file('archivo')->getRealPath(), null);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'      => true,
            'summary' => $summary,
            'message' => "Catálogo global importado: {$summary['imported']} nuevas, {$summary['updated']} actualizadas.",
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
            'tipo'             => ['nullable', 'in:Afectable,Agrupador'],
            'rfc_asociado'     => ['nullable', 'string', 'max:13'],
            'es_afectable'     => ['boolean'],
            'parent_id'        => ['nullable', 'integer', 'exists:accounts,id'],
        ]);
        $data['client_id'] = null;
        $data['activo'] = true;
        $account = Account::create($data);
        return response()->json(['ok' => true, 'message' => 'Cuenta creada.', 'id' => $account->id]);
    }
}
