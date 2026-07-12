<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
    ) {}

    public function index(Request $request): View
    {
        $clients = Client::query()
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('estado'), function ($q) use ($request) {
                $q->where('activo', $request->string('estado') === 'activos');
            })
            ->orderBy('razon_social')
            ->paginate(20)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'q'       => $request->string('q')->toString(),
            'estado'  => $request->string('estado')->toString(),
        ]);
    }

    public function create(): View
    {
        return view('clients.create', ['client' => new Client()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $client = Client::create($data);

        // Selecting a freshly created client as the active one is a natural next step.
        $this->context->setClient($client);

        return redirect()
            ->route('clients.show', $client)
            ->with('toast', ['type' => 'success', 'message' => 'Cliente creado.']);
    }

    public function show(Client $client): View
    {
        $client->load(['periods' => fn ($q) => $q->orderByDesc('year')->orderByDesc('month')]);

        return view('clients.show', ['client' => $client]);
    }

    public function edit(Client $client): View
    {
        return view('clients.edit', ['client' => $client]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request, $client));

        return redirect()
            ->route('clients.show', $client)
            ->with('toast', ['type' => 'success', 'message' => 'Cliente actualizado.']);
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('toast', ['type' => 'success', 'message' => 'Cliente archivado.']);
    }

    /** Set this client as the active working context (used by the client switcher). */
    public function activate(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        $this->context->setClient($client);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'client' => $client->only(['id', 'rfc', 'razon_social'])]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => "Cliente activo: {$client->display_name}"]);
    }

    /** Shared validation for store + update. */
    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'rfc' => [
                'required', 'string', 'max:13',
                Rule::unique('clients', 'rfc')->ignore($client?->id),
            ],
            'razon_social'     => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'regimen_fiscal'   => ['required', Rule::in(['fisica', 'moral'])],
            'codigo_postal'    => ['nullable', 'string', 'size:5'],
            'email'            => ['nullable', 'email', 'max:255'],
            'telefono'         => ['nullable', 'string', 'max:30'],
            'activo'           => ['sometimes', 'boolean'],
            'notas'            => ['nullable', 'string'],
        ]);
    }
}
