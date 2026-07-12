<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCfdiUpload;
use App\Models\CfdiUpload;
use App\Models\Invoice;
use App\Models\Period;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
    ) {}

    /**
     * Invoice browser for the active period. Requires an active client + period;
     * otherwise sends the user back to pick one.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->context->hasPeriod()) {
            return redirect()
                ->route('dashboard')
                ->with('toast', ['type' => 'warning', 'message' => 'Selecciona un cliente y periodo primero.']);
        }

        $period = $this->context->period();

        $invoices = Invoice::where('period_id', $period->id)
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->string('tipo')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(function ($sub) use ($term) {
                    $sub->where('emisor_nombre', 'like', "%{$term}%")
                        ->orWhere('receptor_nombre', 'like', "%{$term}%")
                        ->orWhere('emisor_rfc', 'like', "%{$term}%")
                        ->orWhere('receptor_rfc', 'like', "%{$term}%")
                        ->orWhere('uuid', 'like', "%{$term}%")
                        ->orWhere('folio', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('fecha_emision')
            ->paginate(25)
            ->withQueryString();

        // Totals strip: emitidas (income) vs recibidas (expense) for the period.
        $totals = [
            'emitidas'  => Invoice::where('period_id', $period->id)->where('tipo', 'emitida')->sum('total'),
            'recibidas' => Invoice::where('period_id', $period->id)->where('tipo', 'recibida')->sum('total'),
            'count'     => Invoice::where('period_id', $period->id)->count(),
        ];

        $recentUploads = CfdiUpload::where('period_id', $period->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('invoices.index', [
            'period'        => $period,
            'invoices'      => $invoices,
            'totals'        => $totals,
            'recentUploads' => $recentUploads,
            'tipo'          => $request->string('tipo')->toString(),
            'q'             => $request->string('q')->toString(),
        ]);
    }

    /**
     * Accept a CFDI package (ZIP or single XML), store it, and queue processing.
     * Fast response; the job does the heavy parsing.
     */
    public function upload(Request $request): JsonResponse
    {
        if (! $this->context->hasPeriod()) {
            return response()->json(['message' => 'Selecciona un cliente y periodo primero.'], 422);
        }

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:zip,xml', 'max:51200'], // 50 MB
        ]);

        $period = $this->context->period();
        $client = $this->context->client();

        $file = $request->file('archivo');
        $stored = $file->store("cfdi-uploads/{$client->id}", 'local');

        $upload = CfdiUpload::create([
            'client_id'     => $client->id,
            'period_id'     => $period->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path'   => $stored,
            'size_bytes'    => $file->getSize(),
            'status'        => 'pending',
        ]);

        ProcessCfdiUpload::dispatch($upload->id);

        return response()->json([
            'ok'        => true,
            'upload_id' => $upload->id,
            'message'   => 'Archivo recibido. Procesando…',
        ]);
    }

    /** Poll endpoint for upload progress (the UI checks this after upload). */
    public function status(CfdiUpload $upload): JsonResponse
    {
        return response()->json([
            'status'   => $upload->status,
            'label'    => $upload->statusLabel(),
            'imported' => $upload->imported,
            'skipped'  => $upload->skipped,
            'failed'   => $upload->failed,
            'errors'   => $upload->errors ?? [],
            'fatal'    => $upload->fatal_error,
            'finished' => $upload->isFinished(),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load('lines');

        return view('invoices.show', ['invoice' => $invoice]);
    }

    /** Download the original XML, verbatim (SAT compliance). */
    public function xml(Invoice $invoice)
    {
        abort_if(! $invoice->xml_original, 404, 'XML no disponible.');

        return Response::make($invoice->xml_original, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $invoice->uuid . '.xml"',
        ]);
    }
}
