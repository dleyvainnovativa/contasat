<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Period;
use App\Services\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeriodController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
    ) {}

    /**
     * Open (or create) a period for a client and set it as the active context.
     * This is the entry point into the processing pipeline for a given month.
     */
    public function open(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'year'  => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        // Never open a future period, nor one before the client's inicio de
        // operaciones. Keeps the workable set bounded and consistent with how
        // uploaded CFDIs are routed by date.
        if (! $client->periodInRange((int) $data['year'], (int) $data['month'])) {
            return redirect()
                ->route('clients.show', $client)
                ->with('toast', [
                    'type'    => 'warning',
                    'message' => 'Ese periodo está fuera del rango de operaciones del cliente.',
                ]);
        }

        $period = Period::firstOrCreate(
            ['client_id' => $client->id, 'year' => $data['year'], 'month' => $data['month']],
        );

        $this->context->setPeriod($period);

        return redirect()
            ->route('clients.show', $client)
            ->with('toast', ['type' => 'success', 'message' => "Periodo activo: {$period->label}"]);
    }

    /** Select an existing period as active. */
    public function activate(Period $period): RedirectResponse
    {
        $this->context->setPeriod($period);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Periodo activo: {$period->client->display_name} — {$period->label}",
        ]);
    }
}
