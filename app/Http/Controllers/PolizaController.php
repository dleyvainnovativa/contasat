<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\ProvisionCobroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Generates the provisión and cobro pólizas from the invoice detail view
 * (per-invoice; batch generation is a later pass).
 */
class PolizaController extends Controller
{

    public function __construct(
        private readonly ProvisionCobroService $service,
        private readonly \App\Services\CobroSourceService $sources,   // ADD
    ) {}

    /** Póliza de provisión (accrual). Optional per-concepto revenue accounts. */
    public function provision(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'concept_accounts'   => ['array'],
            'concept_accounts.*' => ['integer', 'exists:accounts,id'],
        ]);

        try {
            $poliza = $this->service->generateProvision($invoice, $data['concept_accounts'] ?? []);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Póliza de provisión generada' . ($poliza->cuadra ? '.' : ' (revisar: no cuadra).'),
            'poliza'  => $this->serialize($poliza),
        ]);
    }

    /** Póliza de cobro (cash). Payment source per D4; here accepts a date. */

    public function cobro(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'origen'           => ['required', 'in:manual,complemento,estado_cuenta'],
            'fecha_pago'       => ['nullable', 'date'],       // required only for manual
            'payment_uuid'     => ['nullable', 'string'],     // chosen complemento
            'bank_movement_id' => ['nullable', 'integer'],    // chosen movement
        ]);

        $origen = $data['origen'];

        // Manual: the user's date is authoritative. Non-manual: resolve from source.
        if ($origen === 'manual') {
            if (empty($data['fecha_pago'])) {
                return response()->json(['message' => 'Ingresa la fecha de pago.'], 422);
            }
            $fecha = $data['fecha_pago'];
            $bankMovementId = null;
            $paymentUuid = null;
        } else {
            $resolved = $this->sources->resolve(
                $invoice,
                $origen,
                $data['payment_uuid'] ?? null,
                $data['bank_movement_id'] ?? null,
            );

            if (! $resolved['found'] || ! $resolved['fecha']) {
                $msg = $origen === 'complemento'
                    ? 'No se encontró un complemento de pago que ampare esta factura.'
                    : 'No se encontró un movimiento conciliado para esta factura.';
                return response()->json(['message' => $msg], 422);
            }

            $fecha = $resolved['fecha'];
            $bankMovementId = $resolved['bank_movement_id'];
            $paymentUuid = $resolved['payment_uuid'];
        }

        try {
            $poliza = $this->service->generateCobro($invoice, $fecha, $origen, $bankMovementId, $paymentUuid);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Póliza de cobro generada' . ($poliza->cuadra ? '.' : ' (revisar: no cuadra).'),
            'poliza'  => $this->serialize($poliza),
        ]);
    }

    private function serialize($poliza): array
    {
        return [
            'id'          => $poliza->id,
            'tipo'        => $poliza->tipo,
            'concepto'    => $poliza->concepto,
            'fecha'       => $poliza->fecha->format('Y-m-d'),
            'cuadra'      => $poliza->cuadra,
            'total_cargo' => $poliza->total_cargo,
            'lines'       => $poliza->lines->map(fn($l) => [
                'numero_cuenta' => $l->numero_cuenta,
                'nombre_cuenta' => $l->nombre_cuenta,
                'cargo'         => $l->cargo,
                'abono'         => $l->abono,
            ]),
        ];
    }
    /**
     * Return the payment candidates for a source, so the UI can show the matched
     * date/amount before the user commits. Called when they pick Opción 2
     * (complemento) or Opción 3 (estado de cuenta).
     */
    public function cobroCandidates(\Illuminate\Http\Request $request, \App\Models\Invoice $invoice): \Illuminate\Http\JsonResponse
    {
        $origen = $request->string('origen')->toString();

        $candidates = match ($origen) {
            'complemento'   => $this->sources->complementoCandidates($invoice),
            'estado_cuenta' => $this->sources->estadoCuentaCandidates($invoice),
            default         => collect(),
        };

        return response()->json([
            'origen'     => $origen,
            'candidates' => $candidates,
            'found'      => $candidates->isNotEmpty(),
        ]);
    }
}
