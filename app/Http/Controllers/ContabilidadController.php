<?php

namespace App\Http\Controllers;

use App\Services\ContabilidadElectronica\BalanzaXmlGenerator;
use App\Services\ContabilidadElectronica\CatalogoXmlGenerator;
use App\Services\ContabilidadElectronica\PolizasXmlGenerator;
use App\Services\ContabilidadElectronica\XmlValidator;
use App\Services\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Contabilidad electrónica (Phase 5) — generates the three SAT XML files from the
 * period's reconciled pólizas, validates each (XSD when bundled, structural
 * otherwise), and offers individual or zipped download. An XML is only "filable"
 * once it validates.
 */
class ContabilidadController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
        private readonly CatalogoXmlGenerator $catalogo,
        private readonly BalanzaXmlGenerator $balanza,
        private readonly PolizasXmlGenerator $polizas,
        private readonly XmlValidator $validator,
    ) {}

    private function requirePeriod(): ?RedirectResponse
    {
        if (! $this->context->hasPeriod()) {
            return redirect()->route('dashboard')
                ->with('toast', ['type' => 'warning', 'message' => 'Selecciona un cliente y periodo primero.']);
        }

        return null;
    }

    /** Overview: generate all three, validate, show status + filename nomenclature. */
    public function index(): View|RedirectResponse
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        $period = $this->context->period();

        $docs = [];
        foreach ($this->generateAll($period) as $type => $xml) {
            $result = $this->validator->validate($xml, $type);
            $docs[$type] = [
                'valid'    => $result['valid'],
                'method'   => $result['method'],
                'errors'   => $result['errors'],
                'filename' => $this->filename($period, $type),
                'size'     => strlen($xml),
            ];
        }

        return view('contabilidad.index', [
            'period' => $period,
            'docs'   => $docs,
        ]);
    }

    public function download(string $type): Response|RedirectResponse
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        $period = $this->context->period();
        $xml = $this->generateOne($period, $type);

        if ($xml === null) {
            return back()->with('toast', ['type' => 'danger', 'message' => 'Tipo de documento inválido.']);
        }

        return response($xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $this->filename($period, $type) . '"',
        ]);
    }

    /** Download all three as a ZIP (SAT expects zipped XML for filing). */
    public function downloadZip(): BinaryFileResponse|RedirectResponse
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        $period = $this->context->period();
        $path = storage_path('app/tmp/contabilidad_' . $period->id . '_' . now()->timestamp . '.zip');
        @mkdir(dirname($path), 0775, true);

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($this->generateAll($period) as $type => $xml) {
            $zip->addFromString($this->filename($period, $type), $xml);
        }
        $zip->close();

        $niceName = "contabilidad_{$period->client->rfc}_{$period->year}-" . str_pad((string) $period->month, 2, '0', STR_PAD_LEFT) . '.zip';

        return response()->download($path, $niceName)->deleteFileAfterSend();
    }

    /** Preview raw XML in the browser. */
    public function preview(string $type): Response|RedirectResponse
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        $xml = $this->generateOne($this->context->period(), $type);
        if ($xml === null) {
            return back();
        }

        return response($xml, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /** @return array<string, string> type => xml */
    private function generateAll($period): array
    {
        return [
            'catalogo' => $this->catalogo->generate($period),
            'balanza'  => $this->balanza->generate($period),
            'polizas'  => $this->polizas->generate($period),
        ];
    }

    private function generateOne($period, string $type): ?string
    {
        return match ($type) {
            'catalogo' => $this->catalogo->generate($period),
            'balanza'  => $this->balanza->generate($period),
            'polizas'  => $this->polizas->generate($period),
            default    => null,
        };
    }

    /**
     * SAT filename nomenclature: RFC + Anio + Mes + type code + .XML
     *   catalogo -> CT, balanza -> BN, polizas -> PL
     */
    private function filename($period, string $type): string
    {
        $code = ['catalogo' => 'CT', 'balanza' => 'BN', 'polizas' => 'PL'][$type] ?? 'XX';
        $mes = str_pad((string) $period->month, 2, '0', STR_PAD_LEFT);

        return "{$period->client->rfc}{$period->year}{$mes}{$code}.XML";
    }
}
