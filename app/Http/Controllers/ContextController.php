<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Period;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authoritative client/period switching for the topbar switcher.
 *
 * The switch MUST go through the server: the active client/period determine which
 * client's books every page operates on, so this is authoritative session state
 * (WorkContext), never client-only. The topbar may cache the client LIST in
 * localStorage for instant rendering, but the act of switching writes here.
 */
class ContextController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
    ) {}

    /** The client list for the switcher dropdown (cached client-side for speed). */
    public function clients(): JsonResponse
    {
        $clients = Client::where('activo', true)
            ->orderBy('razon_social')
            ->get(['id', 'rfc', 'razon_social', 'nombre_comercial'])
            ->map(fn ($c) => [
                'id'    => $c->id,
                'rfc'   => $c->rfc,
                'label' => $c->nombre_comercial ?: $c->razon_social,
            ]);

        return response()->json(['clients' => $clients]);
    }

    /** A client's periods, loaded lazily when that client is selected. */
    public function periods(Client $client): JsonResponse
    {
        $periods = Period::where('client_id', $client->id)
            ->orderByDesc('year')->orderByDesc('month')
            ->get(['id', 'year', 'month', 'status'])
            ->map(fn ($p) => [
                'id'    => $p->id,
                'label' => $this->periodLabel($p),
                'status' => $p->status,
            ]);

        return response()->json(['periods' => $periods]);
    }

    /** Switch the active client (authoritative). Optionally set a period too. */
    public function switch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'period_id' => ['nullable', 'integer', 'exists:periods,id'],
        ]);

        $client = Client::findOrFail($data['client_id']);
        $this->context->setClient($client);

        if (! empty($data['period_id'])) {
            $period = Period::where('id', $data['period_id'])
                ->where('client_id', $client->id)   // guard: period must belong to client
                ->first();
            if ($period) {
                $this->context->setPeriod($period);
            }
        } else {
            // Switching client without a period clears the stale one.
            $this->context->clearPeriod();
        }

        return response()->json(['ok' => true, 'redirect' => route('dashboard')]);
    }

    /** Switch only the period, within the active client. */
    public function switchPeriod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_id' => ['required', 'integer', 'exists:periods,id'],
        ]);

        if (! $this->context->hasClient()) {
            return response()->json(['message' => 'No hay cliente activo.'], 422);
        }

        $period = Period::where('id', $data['period_id'])
            ->where('client_id', $this->context->client()->id)
            ->first();

        if (! $period) {
            return response()->json(['message' => 'El periodo no pertenece al cliente activo.'], 422);
        }

        $this->context->setPeriod($period);

        return response()->json(['ok' => true, 'redirect' => url()->previous()]);
    }

    private function periodLabel(Period $p): string
    {
        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        return ($meses[$p->month] ?? $p->month) . ' ' . $p->year;
    }
}
