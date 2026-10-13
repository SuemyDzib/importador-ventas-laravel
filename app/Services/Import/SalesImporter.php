<?php

namespace App\Services\Import;

use App\Models\Import;
use App\Models\ImportRowError;
use App\Models\Sale;
use DateTime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class SalesImporter
{
    /** Por encima de este límite conviene procesar en una cola (ver README). */
    public const MAX_ROWS = 20000;

    private const INSERT_CHUNK = 500;

    /** Formatos de fecha aceptados como texto, en orden de prioridad (día primero). */
    private const DATE_FORMATS = ['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'Y/m/d', 'd/m/y'];

    public function __construct(private readonly HeaderMapper $headers)
    {
    }

    public function import(UploadedFile $file, bool $strict = false): Import
    {
        $import = Import::create([
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $file->store('imports'),
            'status' => Import::STATUS_PROCESSING,
            'strict' => $strict,
        ]);

        try {
            $rows = $this->readRows($file);
            $this->process($import, $rows);
        } catch (InvalidSpreadsheetException $e) {
            $this->fail($import, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->fail($import, 'No se pudo leer el archivo. Revisa que no esté dañado ni protegido con contraseña.');
        }

        return $import->fresh();
    }

    // ---------------------------------------------------------------------
    // Lectura
    // ---------------------------------------------------------------------

    /** @return array<int, array<int, mixed>> Filas indexadas desde 0 (fila 0 = fila 1 del Excel). */
    private function readRows(UploadedFile $file): array
    {
        $type = match (strtolower($file->getClientOriginalExtension())) {
            'xlsx' => 'Xlsx',
            'xls' => 'Xls',
            'ods' => 'Ods',
            'csv', 'txt' => 'Csv',
            default => throw new InvalidSpreadsheetException('Formato no soportado. Usa .xlsx, .xls, .ods o .csv.'),
        };

        $reader = IOFactory::createReader($type);
        $reader->setReadDataOnly(true); // solo valores: más rápido y con menos memoria

        if ($reader instanceof CsvReader) {
            $reader->setInputEncoding(CsvReader::GUESS_ENCODING); // UTF-8, Windows-1252, etc.
        }

        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getSheet(0);

        // calculateFormulas = true, formatData = false (las fechas llegan como número de serie de Excel)
        $rows = $sheet->toArray(null, true, false, false);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    // ---------------------------------------------------------------------
    // Procesamiento
    // ---------------------------------------------------------------------

    private function process(Import $import, array $rows): void
    {
        $headerIndex = $this->findHeaderRow($rows);
        $mapping = $this->headers->map($rows[$headerIndex]);

        if ($mapping['missing'] !== []) {
            $labels = array_map([HeaderMapper::class, 'label'], $mapping['missing']);
            throw new InvalidSpreadsheetException(
                'Faltan columnas obligatorias en la fila '.($headerIndex + 1).': '.implode(', ', $labels).'.'
            );
        }

        $dataRows = array_filter(
            array_slice($rows, $headerIndex + 1, null, true),
            fn (array $row) => ! $this->isEmptyRow($row)
        );

        if ($dataRows === []) {
            throw new InvalidSpreadsheetException('El archivo tiene encabezados pero ninguna fila de datos.');
        }

        if (count($dataRows) > self::MAX_ROWS) {
            throw new InvalidSpreadsheetException(
                'El archivo tiene '.number_format(count($dataRows)).' filas; el máximo es '.number_format(self::MAX_ROWS).'. Divídelo en varios archivos.'
            );
        }

        $now = now();
        $valid = [];
        $invalid = [];
        $errorCounts = [];
        $seen = []; // huella de la fila => número de fila donde apareció primero

        foreach ($dataRows as $index => $row) {
            $rowNumber = $index + 1;
            $raw = $this->extract($row, $mapping['map']);
            $data = $this->normalize($raw);
            $messages = $this->validate($data);

            if ($messages === []) {
                $fingerprint = md5(implode('|', [
                    $data['fecha'], $data['email'], mb_strtolower($data['producto']),
                    $data['cantidad'], $data['precio_unitario'],
                ]));

                if (isset($seen[$fingerprint])) {
                    $messages[HeaderMapper::ROW] = "Fila duplicada: es idéntica a la fila {$seen[$fingerprint]}.";
                } else {
                    $seen[$fingerprint] = $rowNumber;
                }
            }

            if ($messages !== []) {
                foreach (array_keys($messages) as $column) {
                    $errorCounts[$column] = ($errorCounts[$column] ?? 0) + 1;
                }

                $invalid[] = [
                    'import_id' => $import->id,
                    'row_number' => $rowNumber,
                    'row_values' => json_encode($this->displayValues($raw, $data), JSON_UNESCAPED_UNICODE),
                    'messages' => json_encode($messages, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                continue;
            }

            $valid[] = [
                'import_id' => $import->id,
                'fecha' => $data['fecha'],
                'cliente' => $data['cliente'],
                'email' => $data['email'],
                'producto' => $data['producto'],
                'categoria' => $data['categoria'],
                'cantidad' => (int) $data['cantidad'],
                'precio_unitario' => round((float) $data['precio_unitario'], 2),
                'total' => round((int) $data['cantidad'] * (float) $data['precio_unitario'], 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Modo estricto: si hay un solo error, no se guarda ninguna venta.
        $rejectAll = $import->strict && $invalid !== [];

        DB::transaction(function () use ($import, $valid, $invalid, $rejectAll, $mapping, $headerIndex, $errorCounts) {
            foreach (array_chunk($invalid, self::INSERT_CHUNK) as $chunk) {
                ImportRowError::insert($chunk);
            }

            if (! $rejectAll) {
                foreach (array_chunk($valid, self::INSERT_CHUNK) as $chunk) {
                    Sale::insert($chunk);
                }
            }

            $import->update([
                'total_rows' => count($valid) + count($invalid),
                'valid_rows' => count($valid),
                'invalid_rows' => count($invalid),
                'status' => match (true) {
                    $rejectAll => Import::STATUS_REJECTED,
                    $invalid !== [] => Import::STATUS_WITH_ERRORS,
                    default => Import::STATUS_COMPLETED,
                },
                'meta' => [
                    'header_row' => $headerIndex + 1,
                    'ignored_columns' => $mapping['ignored'],
                    'error_counts' => $errorCounts,
                ],
                'finished_at' => now(),
            ]);
        });
    }

    /** El encabezado es la primera fila con contenido (tolera filas de título vacías arriba). */
    private function findHeaderRow(array $rows): int
    {
        foreach ($rows as $index => $row) {
            if (! $this->isEmptyRow($row)) {
                return $index;
            }
        }

        throw new InvalidSpreadsheetException('El archivo está vacío.');
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> Valores crudos por columna canónica. */
    private function extract(array $row, array $map): array
    {
        $values = [];
        foreach ($map as $index => $key) {
            $values[$key] = $row[$index] ?? null;
        }

        return $values;
    }

    // ---------------------------------------------------------------------
    // Normalización: aquí está la parte "inteligente"
    // ---------------------------------------------------------------------

    private function normalize(array $raw): array
    {
        $email = $this->text($raw['email'] ?? null);
        $categoria = $this->text($raw['categoria'] ?? null);

        return [
            'fecha' => $this->parseDate($raw['fecha'] ?? null),
            'cliente' => $this->text($raw['cliente'] ?? null),
            'email' => $email !== null ? mb_strtolower($email) : null,
            'producto' => $this->text($raw['producto'] ?? null),
            // "ELECTRÓNICA", "electrónica" y "Electrónica" se agrupan igual en el tablero.
            'categoria' => $categoria !== null ? mb_convert_case($categoria, MB_CASE_TITLE, 'UTF-8') : null,
            'cantidad' => $this->parseNumber($raw['cantidad'] ?? null),
            'precio_unitario' => $this->parseNumber($raw['precio_unitario'] ?? null),
        ];
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }

    /**
     * Acepta fechas reales de Excel (número de serie) y texto en formatos comunes.
     * Devuelve Y-m-d si la entiende; si no, el texto original para que la validación lo marque.
     */
    private function parseDate(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $serial = (float) $value;
            if ($serial >= 1 && $serial < 2958466) { // rango válido de fechas en Excel
                try {
                    return ExcelDate::excelToDateTimeObject($serial)->format('Y-m-d');
                } catch (Throwable) {
                    // cae al return de abajo
                }
            }

            return (string) $value;
        }

        $text = trim((string) $value);

        foreach (self::DATE_FORMATS as $format) {
            $date = DateTime::createFromFormat('!'.$format, $text);
            $problems = DateTime::getLastErrors();

            // Rechaza fechas imposibles como 31/02/2026 (PHP las "corrige" con una advertencia).
            $clean = $problems === false || ($problems['warning_count'] === 0 && $problems['error_count'] === 0);

            if ($date !== false && $clean) {
                return $date->format('Y-m-d');
            }
        }

        return $text;
    }

    /** Acepta "1,250.50", "1.250,50", "$ 980", "12,5" y números nativos. */
    private function parseNumber(mixed $value): int|float|string|null
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $original = (string) $value;
        $clean = preg_replace('/\s|\$|€|mxn|usd/iu', '', $original);

        $europeanThousands = preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $clean); // 1.250,50
        $commaDecimal = preg_match('/^-?\d+,\d{1,2}$/', $clean);                   // 12,5

        $clean = ($europeanThousands || $commaDecimal)
            ? str_replace(['.', ','], ['', '.'], $clean)
            : str_replace(',', '', $clean);                                         // 1,250.50

        return is_numeric($clean) ? $clean + 0 : $original;
    }

    /** Valores para mostrar en la tabla de errores: normalizados si se entendieron, crudos si no. */
    private function displayValues(array $raw, array $data): array
    {
        $display = [];

        foreach (HeaderMapper::keys() as $key) {
            $value = $data[$key] ?? null;

            if ($key === 'fecha' && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $value = DateTime::createFromFormat('Y-m-d', $value)->format('d/m/Y');
            }

            $display[$key] = $value === null ? null : (string) $value;
        }

        return $display;
    }

    // ---------------------------------------------------------------------
    // Validación
    // ---------------------------------------------------------------------

    /** @return array<string, string> Columna => primer mensaje de error. */
    private function validate(array $data): array
    {
        $validator = Validator::make($data, $this->rules(), $this->messages());

        $messages = [];
        foreach ($validator->errors()->messages() as $field => $fieldMessages) {
            $messages[$field] = $fieldMessages[0];
        }

        return $messages;
    }

    private function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'cliente' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:filter', 'max:150'],
            'producto' => ['required', 'string', 'max:120'],
            'categoria' => ['required', 'string', 'max:60'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'precio_unitario' => ['required', 'numeric', 'gt:0', 'max:9999999'],
        ];
    }

    /** Mensajes cortos: se muestran dentro de la celda, así que no repiten el nombre de la columna. */
    private function messages(): array
    {
        return [
            'required' => 'Está vacía.',
            'max.string' => 'Supera :max caracteres.',
            'fecha.date_format' => 'Fecha no válida. Usa DD/MM/AAAA.',
            'fecha.before_or_equal' => 'Es una fecha futura.',
            'email.email' => 'No es un correo válido.',
            'cantidad.integer' => 'Debe ser un número entero.',
            'cantidad.min' => 'Debe ser al menos 1.',
            'cantidad.max' => 'No puede superar :max.',
            'precio_unitario.numeric' => 'Debe ser un número.',
            'precio_unitario.gt' => 'Debe ser mayor que 0.',
            'precio_unitario.max' => 'Es demasiado alto.',
        ];
    }

    private function fail(Import $import, string $message): void
    {
        $import->update([
            'status' => Import::STATUS_FAILED,
            'meta' => ['error' => $message],
            'finished_at' => now(),
        ]);
    }
}
