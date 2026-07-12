<?php

namespace App\Services\ContabilidadElectronica;

use App\Models\Period;
use App\Services\PolizaBuilder;
use XMLWriter;

/**
 * Generates the Pólizas del Periodo XML (SAT Anexo 24, version 1.3).
 *
 * Root: PLZ:Polizas in namespace
 *   http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/PolizasPeriodo
 * Required root attributes: Version=1.3, RFC, Mes, Anio, TipoSolicitud (AF|FC|DE|CO).
 *
 * Each PLZ:Poliza: NumUnIdenPol, Fecha, Concepto.
 *   Each PLZ:Transaccion: NumCta, DesCta, Concepto, Debe, Haber.
 *     Each PLZ:CompNal (national CFDI support): UUID_CFDI, RFC, MontoTotal.
 *
 * This is where the UUID threaded through the system since Phase 0 pays off: each
 * transaction line references the CFDI that supports it, exactly as SAT requires.
 */
class PolizasXmlGenerator
{
    private const NS  = 'http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/PolizasPeriodo';
    private const XSD = 'http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/PolizasPeriodo/PolizasPeriodo_1_3.xsd';
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public function __construct(
        private readonly PolizaBuilder $polizas,
    ) {}

    public function generate(Period $period, string $tipoSolicitud = 'AF'): string
    {
        $client = $period->client;
        $polizas = $this->polizas->build($period);

        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->startDocument('1.0', 'UTF-8');

        $w->startElementNs('PLZ', 'Polizas', self::NS);
        $w->writeAttributeNs('xmlns', 'xsi', null, self::XSI);
        $w->writeAttributeNs('xsi', 'schemaLocation', self::XSI, self::NS . ' ' . self::XSD);
        $w->writeAttribute('Version', '1.3');
        $w->writeAttribute('RFC', $client->rfc);
        $w->writeAttribute('Mes', str_pad((string) $period->month, 2, '0', STR_PAD_LEFT));
        $w->writeAttribute('Anio', (string) $period->year);
        $w->writeAttribute('TipoSolicitud', $tipoSolicitud); // AF|FC|DE|CO

        $consecutivo = 1;

        foreach ($polizas as $poliza) {
            $w->startElementNs('PLZ', 'Poliza', null);
            $w->writeAttribute('NumUnIdenPol', $this->polizaId($poliza, $consecutivo));
            $w->writeAttribute('Fecha', $poliza['fecha']);
            $w->writeAttribute('Concepto', $this->clean($poliza['concepto']));

            foreach ($poliza['lines'] as $line) {
                $w->startElementNs('PLZ', 'Transaccion', null);
                $w->writeAttribute('NumCta', $line['numero_cuenta']);
                $w->writeAttribute('DesCta', $this->clean($line['nombre_cuenta']));
                $w->writeAttribute('Concepto', $this->clean($line['concepto']));
                $w->writeAttribute('Debe', $this->money($line['cargo']));
                $w->writeAttribute('Haber', $this->money($line['abono']));

                // National CFDI support — the UUID reference.
                if (! empty($line['uuid'])) {
                    $w->startElementNs('PLZ', 'CompNal', null);
                    $w->writeAttribute('UUID_CFDI', strtoupper($line['uuid']));
                    $w->writeAttribute('RFC', $poliza['rfc'] ?? 'XAXX010101000');
                    $w->writeAttribute('MontoTotal', $this->money((float) ($poliza['monto_total'] ?? 0)));
                    $w->endElement(); // CompNal
                }

                $w->endElement(); // Transaccion
            }

            $w->endElement(); // Poliza
            $consecutivo++;
        }

        $w->endElement(); // Polizas
        $w->endDocument();

        return $w->outputMemory();
    }

    private function polizaId(array $poliza, int $consecutivo): string
    {
        // Egr = egreso, Ingr = ingreso; a stable per-month unique id.
        $prefix = $poliza['tipo'] === 'Ingreso' ? 'Ingr' : 'Egr';

        return $prefix . str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT);
    }

    private function money(float $v): string
    {
        return number_format($v, 2, '.', '');
    }

    /** SAT attributes must not contain the pipe char; also collapse whitespace. */
    private function clean(?string $s): string
    {
        $s = (string) $s;
        $s = str_replace('|', '/', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }
}
