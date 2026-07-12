<?php

namespace App\Services;

use App\Models\Period;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generates downloadable Excel reports. PhpSpreadsheet is pure PHP (no system
 * binaries), so it works on Hostinger shared hosting.
 *
 * Two exports for Phase 4:
 *   - pólizas       (one section per póliza, debit/credit lines, UUID column)
 *   - income/expense summary
 *
 * Returns the path to a temp .xlsx the controller streams and then deletes.
 */
class ReportExporter
{
    private const HEADER_BG = 'FF4F46E5';   // brand indigo
    private const HEADER_FG = 'FFFFFFFF';

    public function polizasXlsx(Period $period, Collection $polizas): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pólizas');

        $sheet->setCellValue('A1', 'Pólizas — ' . $period->client->display_name . ' — ' . $period->label);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $headers = ['Fecha', 'Tipo', 'Cuenta', 'Nombre de cuenta', 'Concepto', 'Cargo', 'Abono'];
        $row = 3;

        foreach ($polizas as $poliza) {
            // Póliza header band
            $sheet->setCellValue("A{$row}", $poliza['concepto']);
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("F{$row}", $poliza['fecha']);
            $sheet->mergeCells("F{$row}:G{$row}");
            $sheet->getStyle("A{$row}:G{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEF2FF');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            // Column headers
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue("{$col}{$row}", $h);
                $col++;
            }
            $this->styleHeaderRow($sheet, $row);
            $row++;

            // Lines
            foreach ($poliza['lines'] as $line) {
                $sheet->setCellValue("A{$row}", $poliza['fecha']);
                $sheet->setCellValue("B{$row}", $poliza['tipo']);
                $sheet->setCellValue("C{$row}", $line['numero_cuenta']);
                $sheet->setCellValue("D{$row}", $line['nombre_cuenta']);
                $sheet->setCellValue("E{$row}", $line['concepto']);
                $sheet->setCellValue("F{$row}", $line['cargo'] ?: null);
                $sheet->setCellValue("G{$row}", $line['abono'] ?: null);
                $row++;
            }

            // Totals
            $sheet->setCellValue("E{$row}", 'Totales');
            $sheet->setCellValue("F{$row}", $poliza['total_cargo']);
            $sheet->setCellValue("G{$row}", $poliza['total_abono']);
            $sheet->getStyle("E{$row}:G{$row}")->getFont()->setBold(true);
            $sheet->getStyle("E{$row}:G{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
            $row += 2;
        }

        $this->money($sheet, "F4:G{$row}");
        foreach (range('A', 'G') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        return $this->write($spreadsheet, "polizas_{$period->id}");
    }

    public function incomeExpenseXlsx(Period $period, array $data): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen');

        $sheet->setCellValue('A1', 'Ingresos y gastos — ' . $period->client->display_name . ' — ' . $period->label);
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $rows = [
            ['Ingresos (facturas emitidas)', $data['ingresos']],
            ['Gastos (facturas recibidas)', $data['gastos']],
            ['Balance', $data['balance']],
            ['IVA trasladado', $data['iva_trasladado']],
            ['IVA acreditable', $data['iva_acreditable']],
        ];
        $r = 3;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$r}", $label);
            $sheet->setCellValue("B{$r}", $value);
            $r++;
        }
        $this->money($sheet, "B3:B{$r}");

        // By client
        $r += 1;
        $sheet->setCellValue("A{$r}", 'Por cliente');
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;
        $sheet->fromArray(['RFC', 'Nombre', 'Facturas', 'Total'], null, "A{$r}");
        $this->styleHeaderRow($sheet, $r);
        $r++;
        foreach ($data['por_cliente'] as $c) {
            $sheet->fromArray([$c['rfc'], $c['nombre'], $c['count'], $c['total']], null, "A{$r}");
            $r++;
        }

        // By supplier
        $r += 1;
        $sheet->setCellValue("A{$r}", 'Por proveedor');
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;
        $sheet->fromArray(['RFC', 'Nombre', 'Facturas', 'Total'], null, "A{$r}");
        $this->styleHeaderRow($sheet, $r);
        $r++;
        foreach ($data['por_proveedor'] as $c) {
            $sheet->fromArray([$c['rfc'], $c['nombre'], $c['count'], $c['total']], null, "A{$r}");
            $r++;
        }

        foreach (range('A', 'D') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        return $this->write($spreadsheet, "ingresos_gastos_{$period->id}");
    }

    private function styleHeaderRow($sheet, int $row): void
    {
        $range = "A{$row}:G{$row}";
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FG);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::HEADER_BG);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function money($sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('$#,##0.00;($#,##0.00)');
    }

    private function write(Spreadsheet $spreadsheet, string $basename): string
    {
        $path = storage_path("app/tmp/{$basename}_" . now()->timestamp . '.xlsx');
        @mkdir(dirname($path), 0775, true);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
