<?php

namespace App\Services\Sat;

use App\Models\Client;
use App\Models\SatCredential;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use RuntimeException;

/**
 * Builds and wraps the phpcfdi/sat-ws-descarga-masiva Service for a given client.
 *
 * We do not hand-roll the SOAP: SAT's descarga masiva requires signed SOAP
 * envelopes that are rejected outright on any error, so we lean on the library
 * (which tracks the service at version 1.5, 2025-05-30).
 *
 * What this class owns is everything the library deliberately leaves to the
 * caller: loading credentials from encrypted storage, and enforcing SAT's v1.5
 * validation rules *before* a request is sent — because a rejected request still
 * counts against the period's lifetime quota.
 */
class SatWebService
{
    /** SAT v1.5: the query period's lower bound is six years back, without time. */
    private const MAX_YEARS_BACK = 6;

    /** SAT v1.5: start must be strictly less than end — minimum two seconds. */
    private const MIN_PERIOD_SECONDS = 2;

    /** Build the library Service from a client's stored e.firma. */
    public function serviceFor(Client $client): Service
    {
        $fiel = $this->fielFor($client);

        return new Service(new FielRequestBuilder($fiel), new GuzzleWebClient());
    }

    /**
     * Load and validate the client's FIEL from encrypted storage.
     *
     * @throws RuntimeException when absent, a CSD rather than a FIEL, or expired.
     */
    /**
     * Load the client's FIEL from encrypted storage, for signing SOAP requests.
     *
     * Here we do want Fiel (not Credential): its only job is to sign, and its
     * isValid() already covers both "is a FIEL" and "is currently in date". The
     * granular diagnostics live in SatCredentialService, at upload time, where
     * the accountant can actually act on them.
     *
     * @throws RuntimeException when absent, a CSD rather than a FIEL, or expired.
     */
    public function fielFor(Client $client): Fiel
    {
        $credential = SatCredential::where('client_id', $client->id)->first();

        if (! $credential) {
            throw new RuntimeException("El cliente {$client->rfc} no tiene e.firma registrada.");
        }

        $fiel = Fiel::create(
            $credential->cer_contents,
            $credential->key_contents,
            $credential->key_password,
        );

        if (! $fiel->isValid()) {
            throw new RuntimeException(
                "La e.firma de {$client->rfc} no es válida. "
                . 'Verifica que sea e.firma (no CSD) y que esté vigente.'
            );
        }

        return $fiel;
    }

    /**
     * Build query parameters with every SAT v1.5 rule applied.
     *
     * @param string $downloadType 'issued' | 'received'
     * @param string $requestType  'xml' | 'metadata'
     * @throws RuntimeException on any rule violation (fail before spending quota).
     */
    public function buildQuery(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        string $downloadType,
        string $requestType,
    ): QueryParameters {
        $this->assertPeriodValid($start, $end);

        $query = QueryParameters::create()
            ->withPeriod(DateTimePeriod::createFromValues(
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
            ))
            ->withDownloadType($downloadType === 'received' ? DownloadType::received() : DownloadType::issued())
            ->withRequestType($requestType === 'xml' ? RequestType::xml() : RequestType::metadata());

        // SAT v1.5: a received + XML request fails unless document status is
        // explicitly active. The library documents this exact guard.
        if ($query->getDownloadType()->isReceived() && $query->getRequestType()->isXml()) {
            $query = $query->withDocumentStatus(DocumentStatus::active());
        }

        // The library can pre-validate; catching errors here avoids burning a
        // request against the period's lifetime quota.
        $errors = $query->validate();
        if ($errors !== []) {
            throw new RuntimeException('Consulta inválida: ' . implode('; ', $errors));
        }

        return $query;
    }

    /**
     * Enforce the two v1.5 period rules before we ever contact SAT.
     * @throws RuntimeException
     */
    public function assertPeriodValid(\DateTimeInterface $start, \DateTimeInterface $end): void
    {
        $seconds = $end->getTimestamp() - $start->getTimestamp();

        if ($seconds < self::MIN_PERIOD_SECONDS) {
            throw new RuntimeException(
                'El SAT ya no permite consultar un instante: la fecha inicial debe ser '
                . 'menor a la final por al menos dos segundos.'
            );
        }

        // Lower bound: today minus six years, with the time zeroed.
        $lowerBound = new \DateTimeImmutable(
            (new \DateTimeImmutable('today'))
                ->modify('-' . self::MAX_YEARS_BACK . ' years')
                ->format('Y-m-d 00:00:00')
        );

        if ($start < $lowerBound) {
            throw new RuntimeException(
                'El SAT no permite consultar antes de ' . $lowerBound->format('Y-m-d')
                . ' (seis años hacia atrás).'
            );
        }

        if ($end > new \DateTimeImmutable('now')) {
            throw new RuntimeException('La fecha final no puede estar en el futuro.');
        }
    }

    /**
     * The exact period for a calendar month, respecting SAT's rules.
     *
     * The end is clamped to "now" for the month in progress — otherwise the
     * current month is unrequestable, since its last day lies in the future and
     * SAT rejects a future end date. A partial month is still worth downloading.
     *
     * Keeping period construction in one place also makes the (client, type,
     * period) uniqueness key stable and reproducible.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public function monthPeriod(int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $end = $start->modify('last day of this month')->setTime(23, 59, 59);

        $now = new \DateTimeImmutable('now');
        if ($end > $now) {
            // Round down to the previous whole second, keeping the two-second
            // minimum intact for any month that has already begun.
            $end = $now->modify('-1 second');
        }

        return [$start, $end];
    }
}
