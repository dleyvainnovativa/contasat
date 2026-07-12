<?php

namespace App\Services\Sat;

use App\Models\Client;
use App\Models\SatCredential;
use PhpCfdi\Credentials\Credential;
use RuntimeException;
use Throwable;

/**
 * Validates and stores a client's e.firma.
 *
 * Before persisting anything we construct a Credential from the uploaded files.
 * That proves three things at once: the .key password is correct, the certificate
 * and key are a matching pair, and it's a FIEL rather than a CSD. Storing an
 * unverified credential would only surface the problem hours later inside a
 * queued job — a miserable place to discover a typo'd password.
 *
 * API note: we use phpcfdi/credentials' Credential here, NOT the library's Fiel.
 * Fiel is a thin signing wrapper — it keeps its Credential private and exposes
 * only sign(), isValid(), getRfc() and a couple of certificate strings. It cannot
 * tell us validity dates, nor distinguish "this is a CSD" from "this expired".
 * Credential owns all of that, and is already a dependency of the SAT library.
 */
class SatCredentialService
{
    /**
     * @param string $cerContents raw .cer bytes (DER, as SAT ships it)
     * @param string $keyContents raw .key bytes (DER)
     * @param string $password    the private key password
     *
     * @throws RuntimeException when the credential is unusable or mismatched.
     */
    public function store(Client $client, string $cerContents, string $keyContents, string $password): SatCredential
    {
        $credential = $this->buildAndVerify($cerContents, $keyContents, $password);

        $certificate = $credential->certificate();
        $cerRfc = strtoupper(trim($credential->rfc()));

        // Guard against uploading the wrong client's e.firma — an easy mistake at
        // 50 clients, and one that would silently download someone else's CFDI.
        if ($cerRfc !== strtoupper($client->rfc)) {
            throw new RuntimeException(
                "El RFC de la e.firma ({$cerRfc}) no coincide con el del cliente ({$client->rfc})."
            );
        }

        return SatCredential::updateOrCreate(
            ['client_id' => $client->id],
            [
                'cer_contents' => $cerContents,
                'key_contents' => $keyContents,
                'key_password' => $password,
                'cer_rfc'      => $cerRfc,
                // Decimal notation: the SAT web service signs with it, and unlike
                // bytes() every character is printable.
                'cer_serial'   => $certificate->serialNumber()->decimal(),
                'valid_from'   => $certificate->validFromDateTime(),
                'valid_to'     => $certificate->validToDateTime(),
                'is_fiel'      => true,
            ],
        );
    }

    /**
     * Construct the Credential and assert it's usable.
     *
     * The two checks are kept separate rather than leaning on a single boolean:
     * "you uploaded a CSD" and "your certificate expired" are different problems
     * with different fixes, and the accountant deserves to be told which.
     */
    private function buildAndVerify(string $cer, string $key, string $password): Credential
    {
        try {
            $credential = Credential::create($cer, $key, $password);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'No se pudo leer la e.firma. Verifica que el .cer y el .key correspondan '
                . 'entre sí y que la contraseña sea correcta.',
                previous: $e,
            );
        }

        $certificate = $credential->certificate();

        if (! $certificate->satType()->isFiel()) {
            throw new RuntimeException(
                'El certificado es un sello digital (CSD), no una e.firma. '
                . 'Los CSD no sirven para la descarga masiva.'
            );
        }

        if (! $certificate->validOn()) {
            $validTo = $certificate->validToDateTime()->format('d/m/Y');
            throw new RuntimeException("La e.firma no está vigente (venció el {$validTo}).");
        }

        return $credential;
    }

    public function forget(Client $client): void
    {
        SatCredential::where('client_id', $client->id)->delete();
    }
}