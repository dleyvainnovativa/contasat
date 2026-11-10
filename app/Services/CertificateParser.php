<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Reads a SAT .cer certificate (e.firma or CSD) and extracts its expiration
 * date and serial number. SAT .cer files are DER-encoded X.509 certificates;
 * openssl needs PEM, so we wrap the DER before parsing. Pure openssl, no shelling
 * out — safe on Hostinger shared hosting.
 */
class CertificateParser
{
    /**
     * @return array{expira: ?Carbon, serial: ?string}
     */
    public function parse(string $path): array
    {
        $empty = ['expira' => null, 'serial' => null];

        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return $empty;
        }

        $info = @openssl_x509_parse($this->toPem($bytes));
        if (! is_array($info)) {
            return $empty;
        }

        return [
            'expira' => isset($info['validTo_time_t'])
                ? Carbon::createFromTimestampUTC($info['validTo_time_t'])
                : null,
            'serial' => $info['serialNumberHex'] ?? ($info['serialNumber'] ?? null),
        ];
    }

    /** Wrap raw DER bytes as PEM; pass through if it already looks like PEM. */
    private function toPem(string $bytes): string
    {
        if (str_contains($bytes, '-----BEGIN CERTIFICATE-----')) {
            return $bytes;
        }

        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($bytes), 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }
}
