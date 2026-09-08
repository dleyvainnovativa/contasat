<?php

namespace App\Services;

use App\Mail\ExpedienteFiscal;
use App\Mail\SolicitudEstadoCuenta;
use App\Models\ActivityDocument;
use App\Models\Client;
use App\Models\Period;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Email-type Calendario activities: Solicitud (ask the client for their bank
 * statement) and Expediente Fiscal (generate a one-page period summary PDF and
 * email it). Both record the send in activity_documents and mark the activity
 * realizada via that record.
 */
class ActivityEmailService
{
    public const EMAIL_ACTIVITIES = ['edo_cuenta_solicitud', 'expediente_fiscal'];

    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function isEmailActivity(string $activityKey): bool
    {
        return in_array($activityKey, self::EMAIL_ACTIVITIES, true);
    }

    /** The default, editable body for the Solicitud email. */
    public function defaultSolicitudBody(Client $client, Period $period): string
    {
        return "Estimado(a) {$client->display_name},\n\n"
            . "Le solicitamos amablemente el estado de cuenta bancario correspondiente "
            . "al periodo {$period->label}, para continuar con el registro de su contabilidad.\n\n"
            . "Quedamos atentos a su respuesta.";
    }

    /** Send the Solicitud email (body may be edited by the accountant). */
    public function sendSolicitud(Client $client, Period $period, string $body): ActivityDocument
    {
        $to = $this->requireEmail($client);

        Mail::to($to)->send(new SolicitudEstadoCuenta($client, $period, $body));

        return $this->recordSend($client, $period, 'edo_cuenta_solicitud', $to, 'Solicitud enviada.');
    }

    /** Generate the Expediente PDF and email it to the client. */
    public function sendExpediente(Client $client, Period $period): ActivityDocument
    {
        $to = $this->requireEmail($client);

        [$pdfBytes, $pdfName] = $this->buildExpedientePdf($client, $period);

        Mail::to($to)->send(new ExpedienteFiscal($client, $period, $pdfBytes, $pdfName));

        return $this->recordSend($client, $period, 'expediente_fiscal', $to, 'Expediente generado y enviado.');
    }

    /** Build the one-page summary PDF; returns [bytes, filename]. */
    public function buildExpedientePdf(Client $client, Period $period): array
    {
        $ie = $this->reports->incomeExpense($period);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.expediente_fiscal', [
            'client'         => $client,
            'period'         => $period,
            'ingresos'       => $ie['ingresos'],
            'gastos'         => $ie['gastos'],
            'balance'        => $ie['balance'],
            'ivaTrasladado'  => $ie['iva_trasladado'],
            'ivaAcreditable' => $ie['iva_acreditable'],
            'invoiceCount'   => (int) $period->invoice_count,
            'movementCount'  => (int) $period->movement_count,
            'matchedCount'   => (int) $period->matched_count,
            'unmatchedCount' => (int) $period->unmatched_count,
        ]);

        $name = "expediente_fiscal_{$client->rfc}_{$period->label}.pdf";

        return [$pdf->output(), str_replace(' ', '_', $name)];
    }

    private function requireEmail(Client $client): string
    {
        $email = trim((string) $client->email);
        if ($email === '') {
            throw new RuntimeException('Este cliente no tiene correo registrado. Agrégalo en la ficha del cliente.');
        }
        return $email;
    }

    /** Upsert the send record (one per client/period/activity) → marks realizada. */
    private function recordSend(Client $client, Period $period, string $activityKey, string $to, string $detalle): ActivityDocument
    {
        return ActivityDocument::updateOrCreate(
            ['client_id' => $client->id, 'period_id' => $period->id, 'activity_key' => $activityKey],
            [
                'detalle'     => $detalle,
                'sent_at'     => now(),
                'sent_to'     => $to,
                'uploaded_by' => Auth::id(),
            ],
        );
    }
}
