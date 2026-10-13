<?php

namespace App\Services\Import;

use App\Models\Import;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetExporter
{
    private const HEADER_FILL = 'FF22577A';
    private const ERROR_FILL = 'FFF8D9D2';

    /** Plantilla vacía con los encabezados correctos y dos filas de ejemplo. */
    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Ventas');

        $labels = array_map(fn ($c) => $c['label'], HeaderMapper::COLUMNS);
        $sheet->fromArray(array_values($labels), null, 'A1');
        $sheet->fromArray([
            ['15/03/2026', 'Ana López', 'ana.lopez@ejemplo.com', 'Audífonos inalámbricos', 'Electrónica', 2, 899.00],
            ['16/03/2026', 'Comercial del Sureste', 'compras@sureste.mx', 'Silla ergonómica', 'Oficina', 1, 3450.50],
        ], null, 'A2');

        $this->styleHeader($sheet, count($labels));

        // Ayudas en Excel: cantidad entera ≥ 1 y precio > 0 en las primeras 1,000 filas.
        $this->addValidation($sheet, 'F2:F1000', DataValidation::TYPE_WHOLE, '1', 'La cantidad debe ser un entero mayor o igual a 1.');
        $this->addValidation($sheet, 'G2:G1000', DataValidation::TYPE_DECIMAL, '0.01', 'El precio debe ser mayor que 0.');

        return $this->download($spreadsheet, 'plantilla_ventas.xlsx');
    }

    /**
     * Archivo con solo las filas rechazadas: celdas con error en rojo, comentario con el motivo
     * y una columna final "Errores". El usuario corrige aquí y lo vuelve a subir.
     */
    public function errors(Import $import): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Filas con errores');

        $keys = HeaderMapper::keys();
        $headers = array_merge(
            array_map([HeaderMapper::class, 'label'], $keys),
            ['Errores', 'Fila original']
        );
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, count($headers));

        $line = 2;
        foreach ($import->rowErrors()->orderBy('row_number')->lazy(500) as $rowError) {
            foreach ($keys as $i => $key) {
                $cell = Coordinate::stringFromColumnIndex($i + 1).$line;
                $sheet->setCellValueExplicit($cell, $rowError->row_values[$key] ?? '', DataType::TYPE_STRING);

                if ($rowError->hasErrorIn($key)) {
                    $sheet->getStyle($cell)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB(self::ERROR_FILL);
                    $sheet->getComment($cell)->getText()->createTextRun($rowError->messages[$key]);
                }
            }

            $summary = collect($rowError->messages)
                ->map(fn ($message, $key) => HeaderMapper::label($key).': '.$message)
                ->implode(' | ');

            $sheet->setCellValue(Coordinate::stringFromColumnIndex(count($keys) + 1).$line, $summary);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(count($keys) + 2).$line, $rowError->row_number);
            $line++;
        }

        $name = pathinfo($import->original_name, PATHINFO_FILENAME).'_errores.xlsx';

        return $this->download($spreadsheet, $name);
    }

    private function styleHeader(Worksheet $sheet, int $columns): void
    {
        $last = Coordinate::stringFromColumnIndex($columns);
        $style = $sheet->getStyle("A1:{$last}1");
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);

        for ($i = 1; $i <= $columns; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $sheet->freezePane('A2');
    }

    private function addValidation(Worksheet $sheet, string $range, string $type, string $min, string $message): void
    {
        $validation = new DataValidation();
        $validation->setType($type)
            ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)
            ->setFormula1($min)
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Valor no válido')
            ->setError($message);

        $sheet->setDataValidation($range, $validation);
    }

    private function download(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
