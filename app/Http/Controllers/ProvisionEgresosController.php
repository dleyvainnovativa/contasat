<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\ProvisionCobroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gasto (egreso) póliza actions, invoked from the wide table now embedded in the
 * /invoices/gasto tab (see resources/views/invoices/_gasto_wide.blade.php).
 *
 * The standalone Provisión de Egresos page was retired when the wide table moved
 * into the Facturas submenu; only these two action endpoints remain.
 */
class ProvisionEgresosController extends Controller
{
    public function __construct(
        private readonly ProvisionCobroService $provisiones,
    ) {}

    /** Generate the póliza de provisión de gasto for one invoice. */
    public function provision(Invoice $invoice): JsonResponse
    {
        try {
            $poliza = $this->provisiones->generateProvisionGasto($invoice);
            return response()->json(['ok' => true, 'poliza' => ['num_iden' => $poliza->num_iden], 'message' => "Provisión {$poliza->num_iden} generada."]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Generate the póliza de pago de gasto (manual date) for one invoice. */
    public function pago(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'fecha_pago' => ['nullable', 'date'],
        ]);

        try {
            $poliza = $this->provisiones->generatePagoGasto($invoice, $data['fecha_pago'] ?? null);
            return response()->json(['ok' => true, 'poliza' => ['num_iden' => $poliza->num_iden], 'message' => "Pago {$poliza->num_iden} generado."]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
