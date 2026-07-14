<?php

namespace App\Services\ContabilidadElectronica;

use App\Models\Account;
use App\Models\Period;
use XMLWriter;

/**
 * Generates the Catálogo de Cuentas XML (SAT Anexo 24, version 1.3).
 *
 * Root: catalogocuentas:Catalogo in namespace
 *   http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/CatalogoCuentas
 * Required root attributes: Version=1.3, RFC, Mes (MM), Anio (YYYY).
 * Each Ctas element: CodAgrup, NumCta, Desc, SubCtaDe (optional), Nivel, Natur (D|A).
 */
class CatalogoXmlGenerator
{
    private const NS  = 'http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/CatalogoCuentas';
    private const XSD = 'http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/CatalogoCuentas/CatalogoCuentas_1_3.xsd';
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public function generate(Period $period): string
    {
        $client = $period->client;

        $accounts = Account::where('client_id', $client->id)
            ->where('activo', true)
            ->orderBy('numero_cuenta')
            ->get();

        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->startDocument('1.0', 'UTF-8');

        $w->startElementNs('catalogocuentas', 'Catalogo', self::NS);
        // $w->writeAttributeNs('xmlns', 'xsi', null, self::XSI);
        // $w->writeAttributeNs('xsi', 'schemaLocation', self::XSI, self::NS . ' ' . self::XSD);
        $w->writeAttribute('xmlns:xsi', self::XSI);
        $w->writeAttribute('xsi:schemaLocation', self::NS . ' ' . self::XSD);
        $w->writeAttribute('Version', '1.3');
        $w->writeAttribute('RFC', $client->rfc);
        $w->writeAttribute('Mes', str_pad((string) $period->month, 2, '0', STR_PAD_LEFT));
        $w->writeAttribute('Anio', (string) $period->year);

        foreach ($accounts as $account) {
            $w->startElementNs('catalogocuentas', 'Ctas', null);
            $w->writeAttribute('CodAgrup', $account->codigo_agrupador);
            $w->writeAttribute('NumCta', $account->numero_cuenta);
            $w->writeAttribute('Desc', $account->nombre);

            // SubCtaDe is the parent account's NumCta, when this is a subaccount.
            if ($account->parent && $account->parent->numero_cuenta) {
                $w->writeAttribute('SubCtaDe', $account->parent->numero_cuenta);
            }

            $w->writeAttribute('Nivel', (string) $account->nivel);
            $w->writeAttribute('Natur', $account->naturaleza); // D | A
            $w->endElement();
        }

        $w->endElement(); // Catalogo
        $w->endDocument();

        return $w->outputMemory();
    }
}
