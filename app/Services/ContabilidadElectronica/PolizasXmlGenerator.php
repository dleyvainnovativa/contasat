<?php

namespace App\Services\ContabilidadElectronica;

use App\Models\Period;
use App\Models\Poliza;
use XMLWriter;

/**
 * Pólizas del Periodo XML (SAT Anexo 24 v1.3) — D3.5 rework.
 *
 * Now reads the PERSISTED provisión/cobro pólizas (the polizas table) instead of
 * computing one combined entry on the fly. This means what you file to SAT is the
 * real accrual split: a provisión póliza (income recognized) and a separate cobro
 * póliza (cash received), each with its own UUID reference — which is exactly what
 * the Anexo 24 spec asks for ("identificar el soporte documental, tanto en la
 * provisión, como en el pago y/o cobro").
 *
 * Structure (unchanged from Phase 5, validated):
 *   PLZ:Polizas (Version, RFC, Mes, Anio, TipoSolicitud)
 *     PLZ:Poliza (NumUnIdenPol, Fecha, Concepto)
 *       PLZ:Transaccion (NumCta, DesCta, Concepto, Debe, Haber)
 *         PLZ:CompNal (UUID_CFDI, RFC, MontoTotal)
 */
class PolizasXmlGenerator
{
    private const NS  = 'http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/PolizasPeriodo';
    private const XSD = 'http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/PolizasPeriodo/PolizasPeriodo_1_3.xsd';
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public function generate(Period $period, string $tipoSolicitud = 'AF'): string
    {
        $client = $period->client;

        // Persisted pólizas for the period, with their lines and the backing
        // invoice (for the CompNal RFC + monto).
        $polizas = Poliza::where('period_id', $period->id)
            ->with(['lines', 'invoice'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->startDocument('1.0', 'UTF-8');

        $w->startElementNs('PLZ', 'Polizas', self::NS);
        $w->writeAttribute('xmlns:xsi', self::XSI);
        $w->writeAttribute('xsi:schemaLocation', self::NS . ' ' . self::XSD);
        $w->writeAttribute('Version', '1.3');
        $w->writeAttribute('RFC', $client->rfc);
        $w->writeAttribute('Mes', str_pad((string) $period->month, 2, '0', STR_PAD_LEFT));
        $w->writeAttribute('Anio', (string) $period->year);
        $w->writeAttribute('TipoSolicitud', $tipoSolicitud);

        foreach ($polizas as $poliza) {
            $w->startElementNs('PLZ', 'Poliza', null);
            $w->writeAttribute('NumUnIdenPol', $poliza->num_iden ?: ($poliza->tipoLabel() . $poliza->id));
            $w->writeAttribute('Fecha', $poliza->fecha->format('Y-m-d'));
            $w->writeAttribute('Concepto', $this->clean($poliza->concepto));

            // The backing invoice supplies the CompNal RFC + MontoTotal, shared by
            // every line that references a UUID.
            $inv = $poliza->invoice;
            $supportRfc = $inv
                ? ($inv->tipo === 'emitida' ? $inv->receptor_rfc : $inv->emisor_rfc)
                : 'XAXX010101000';
            $supportMonto = $inv ? (float) $inv->total : 0.0;

            foreach ($poliza->lines as $line) {
                $w->startElementNs('PLZ', 'Transaccion', null);
                $w->writeAttribute('NumCta', $line->numero_cuenta);
                $w->writeAttribute('DesCta', $this->clean($line->nombre_cuenta ?? $line->numero_cuenta));
                $w->writeAttribute('Concepto', $this->clean($line->concepto ?? $poliza->concepto));
                $w->writeAttribute('Debe', $this->money((float) $line->cargo));
                $w->writeAttribute('Haber', $this->money((float) $line->abono));

                // CompNal — the UUID reference. Present on both provisión and cobro
                // lines (SAT wants the support in both), whenever the line carries a
                // UUID.
                if (! empty($line->uuid)) {
                    $w->startElementNs('PLZ', 'CompNal', null);
                    $w->writeAttribute('UUID_CFDI', strtoupper($line->uuid));
                    $w->writeAttribute('RFC', $supportRfc);
                    $w->writeAttribute('MontoTotal', $this->money($supportMonto));
                    $w->endElement(); // CompNal
                }

                $w->endElement(); // Transaccion
            }

            $w->endElement(); // Poliza
        }

        $w->endElement(); // Polizas
        $w->endDocument();

        return $w->outputMemory();
    }

    private function money(float $v): string
    {
        return number_format($v, 2, '.', '');
    }

    private function clean(?string $s): string
    {
        $s = (string) $s;
        $s = str_replace('|', '/', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }
}
