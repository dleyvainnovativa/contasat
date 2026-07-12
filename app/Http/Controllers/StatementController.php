<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBankStatement;
use App\Models\BankStatement;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatementController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->context->hasPeriod()) {
            return redirect()
                ->route('dashboard')
                ->with('toast', ['type' => 'warning', 'message' => 'Selecciona un cliente y periodo primero.']);
        }

        $period = $this->context->period();

        $statements = BankStatement::where('period_id', $period->id)
            ->withCount('movements')
            ->latest()
            ->get();

        return view('statements.index', [
            'period'     => $period,
            'statements' => $statements,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        if (! $this->context->hasPeriod()) {
            return response()->json(['message' => 'Selecciona un cliente y periodo primero.'], 422);
        }

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:20480'], // 20 MB
        ]);

        $client = $this->context->client();
        $period = $this->context->period();

        $file = $request->file('archivo');
        $stored = $file->store("statements/{$client->id}", 'local');

        $statement = BankStatement::create([
            'client_id'         => $client->id,
            'period_id'         => $period->id,
            'pdf_path'          => $stored,
            'extraccion_status' => 'pendiente',
        ]);

        ProcessBankStatement::dispatch($statement->id);

        return response()->json([
            'ok'           => true,
            'statement_id' => $statement->id,
            'message'      => 'Estado de cuenta recibido. Extrayendo…',
        ]);
    }

    public function status(BankStatement $statement): JsonResponse
    {
        return response()->json([
            'status'         => $statement->extraccion_status,
            'label'          => $statement->statusLabel(),
            'balance_cuadra' => $statement->balance_cuadra,
            'error'          => $statement->extraccion_error,
            'finished'       => in_array($statement->extraccion_status, ['ok', 'revision', 'error'], true),
        ]);
    }

    public function show(BankStatement $statement): View
    {
        $statement->load(['movements' => fn ($q) => $q->orderBy('fecha')->orderBy('id')]);

        // Recompute the balance breakdown for the verdict panel.
        $cargos    = $statement->movements->sum('cargo');
        $depositos = $statement->movements->sum('deposito');
        $computed  = ($statement->saldo_inicial ?? 0) + $depositos - $cargos;

        return view('statements.show', [
            'statement' => $statement,
            'sumCargos' => $cargos,
            'sumDepositos' => $depositos,
            'computedFinal' => $computed,
            'difference' => $statement->saldo_final !== null ? ($computed - $statement->saldo_final) : null,
        ]);
    }

    /** Re-run extraction (e.g. after adding a bank profile). */
    public function reextract(BankStatement $statement): JsonResponse
    {
        $statement->update(['extraccion_status' => 'pendiente', 'extraccion_error' => null]);
        ProcessBankStatement::dispatch($statement->id);

        return response()->json(['ok' => true, 'message' => 'Reprocesando…']);
    }

    public function destroy(BankStatement $statement): RedirectResponse
    {
        // Clean up the stored PDF then delete (movements cascade).
        if ($statement->pdf_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($statement->pdf_path);
        }
        $statement->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Estado de cuenta eliminado.']);
    }
}
