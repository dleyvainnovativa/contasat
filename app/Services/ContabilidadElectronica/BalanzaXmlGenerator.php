<?php

namespace App\Services\ContabilidadElectronica;

use App\Models\Period;
use App\Models\PolizaLine;
use XMLWriter;

/**
 * Balanza de Comprobación XML (SAT Anexo 24 v1.3) — D3.5 rework.
 *
 * Aggregates per-account debits/credits from the PERSISTED póliza lines (the
 * provisión + cobro entries), instead of from the old on-the-fly builder. Because
 * provisión and cobro both post to the receivable (one debits it, the other
 * credits it), the balanza now reflects the true movement through 105.01.# — the
 * receivable shows activity and nets correctly, which the old combined entry
 * couldn't represent.
 *
 * SaldoIni remains 0 in this build (opening-balance carry-forward is future work);
 * Debe/Haber and the derived SaldoFin are what the monthly filing validates.
 */
class BalanzaXmlGenerator
{
    private const NS  = 'http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/BalanzaComprobacion';
    private const XSD = 'http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/BalanzaComprobacion/BalanzaComprobacion_1_3.xsd';
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public function generate(Period $period, string $tipoEnvio = 'N'): string
    {
        $client = $period->client;
        $totals = $this->accountTotals($period);

        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->startDocument('1.0', 'UTF-8');

        $w->startElementNs('BCE', 'Balanza', self::NS);
        $w->writeAttribute('xmlns:xsi', self::XSI);
        $w->writeAttribute('xsi:schemaLocation', self::NS . ' ' . self::XSD);
        $w->writeAttribute('Version', '1.3');
        $w->writeAttribute('RFC', $client->rfc);
        $w->writeAttribute('Mes', str_pad((string) $period->month, 2, '0', STR_PAD_LEFT));
        $w->writeAttribute('Anio', (string) $period->year);
        $w->writeAttribute('TipoEnvio', $tipoEnvio);

        foreach ($totals as $numCta => $t) {
            $saldoIni = 0.0;
            $debe  = round($t['debe'], 2);
            $haber = round($t['haber'], 2);
            $saldoFin = round($saldoIni + $debe - $haber, 2);

            $w->startElementNs('BCE', 'Ctas', null);
            $w->writeAttribute('NumCta', (string) $numCta);
            $w->writeAttribute('SaldoIni', $this->money($saldoIni));
            $w->writeAttribute('Debe', $this->money($debe));
            $w->writeAttribute('Haber', $this->money($haber));
            $w->writeAttribute('SaldoFin', $this->money($saldoFin));
            $w->endElement();
        }

        $w->endElement(); // Balanza
        $w->endDocument();

        return $w->outputMemory();
    }

    /**
     * Aggregate debit/credit per account number from the period's persisted póliza
     * lines.
     *
     * @return array<string, array{debe:float, haber:float}>
     */
    private function accountTotals(Period $period): array
    {
        $totals = [];

        PolizaLine::whereHas('poliza', fn ($q) => $q->where('period_id', $period->id))
            ->get(['numero_cuenta', 'cargo', 'abono'])
            ->each(function ($line) use (&$totals) {
                $num = $line->numero_cuenta;
                if ($num === '(sin cuenta)') {
                    return;
                }
                $totals[$num]['debe']  = ($totals[$num]['debe'] ?? 0) + (float) $line->cargo;
                $totals[$num]['haber'] = ($totals[$num]['haber'] ?? 0) + (float) $line->abono;
            });

        ksort($totals);

        return $totals;
    }

    private function money(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
}
