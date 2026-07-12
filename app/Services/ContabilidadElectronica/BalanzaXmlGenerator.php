<?php

namespace App\Services\ContabilidadElectronica;

use App\Models\Period;
use App\Services\PolizaBuilder;
use XMLWriter;

/**
 * Generates the Balanza de Comprobación XML (SAT Anexo 24, version 1.3).
 *
 * Root: BCE:Balanza in namespace
 *   http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/BalanzaComprobacion
 * Required root attributes: Version=1.3, RFC, Mes, Anio, TipoEnvio (N|C).
 * Each Ctas: NumCta, SaldoIni, Debe, Haber, SaldoFin.
 *
 * The period's debits/credits per account come from the pólizas. Opening balances
 * (SaldoIni) are 0 in this build — carrying forward prior-period closing balances
 * is a future enhancement; the movement columns (Debe/Haber) and the derived
 * SaldoFin are the part the monthly filing actually validates.
 */
class BalanzaXmlGenerator
{
    private const NS  = 'http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/BalanzaComprobacion';
    private const XSD = 'http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/BalanzaComprobacion/BalanzaComprobacion_1_3.xsd';
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public function __construct(
        private readonly PolizaBuilder $polizas,
    ) {}

    public function generate(Period $period, string $tipoEnvio = 'N'): string
    {
        $client = $period->client;
        $totals = $this->accountTotals($period);

        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->startDocument('1.0', 'UTF-8');

        $w->startElementNs('BCE', 'Balanza', self::NS);
        $w->writeAttributeNs('xmlns', 'xsi', null, self::XSI);
        $w->writeAttributeNs('xsi', 'schemaLocation', self::XSI, self::NS . ' ' . self::XSD);
        $w->writeAttribute('Version', '1.3');
        $w->writeAttribute('RFC', $client->rfc);
        $w->writeAttribute('Mes', str_pad((string) $period->month, 2, '0', STR_PAD_LEFT));
        $w->writeAttribute('Anio', (string) $period->year);
        $w->writeAttribute('TipoEnvio', $tipoEnvio); // N = Normal, C = Complementaria

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
     * Aggregate debit/credit per account number from the period's pólizas.
     * @return array<string, array{debe:float, haber:float}>
     */
    private function accountTotals(Period $period): array
    {
        $totals = [];

        foreach ($this->polizas->build($period) as $poliza) {
            foreach ($poliza['lines'] as $line) {
                $num = $line['numero_cuenta'];
                if ($num === '(sin cuenta)') {
                    continue;
                }
                $totals[$num]['debe']  = ($totals[$num]['debe'] ?? 0) + $line['cargo'];
                $totals[$num]['haber'] = ($totals[$num]['haber'] ?? 0) + $line['abono'];
            }
        }

        ksort($totals);

        return $totals;
    }

    private function money(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
}
