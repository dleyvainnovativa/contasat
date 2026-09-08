<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\Period;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email asking the client for their bank statement (estado de cuenta) for a
 * period. The body text is prefilled but editable by the accountant before
 * sending (passed in as $body).
 */
class SolicitudEstadoCuenta extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public Period $period,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Solicitud de estado de cuenta — {$this->period->label}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitud_estado_cuenta',
            with: ['body' => $this->body],
        );
    }
}
