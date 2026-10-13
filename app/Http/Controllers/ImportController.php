<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadSpreadsheetRequest;
use App\Models\Import;
use App\Services\Import\HeaderMapper;
use App\Services\Import\SalesImporter;
use App\Services\Import\SpreadsheetExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(
        private readonly SalesImporter $importer,
        private readonly SpreadsheetExporter $exporter,
    ) {
    }

    public function index(): View
    {
        return view('imports.index', [
            'imports' => Import::latest()->paginate(10),
            'columns' => HeaderMapper::COLUMNS,
        ]);
    }

    public function store(UploadSpreadsheetRequest $request): RedirectResponse
    {
        $import = $this->importer->import(
            $request->file('archivo'),
            $request->boolean('estricto'),
        );

        return redirect()->route('imports.show', $import);
    }

    public function show(Request $request, Import $import): View
    {
        $allowed = array_merge(HeaderMapper::keys(), [HeaderMapper::ROW]);
        $column = in_array($request->query('columna'), $allowed, true) ? $request->query('columna') : null;

        $failedRows = $import->rowErrors()
            ->when($column, fn ($query) => $query->whereNotNull("messages->{$column}"))
            ->orderBy('row_number')
            ->paginate(25)
            ->withQueryString();

        return view('imports.show', [
            'import' => $import,
            'failedRows' => $failedRows,
            'columns' => HeaderMapper::COLUMNS,
            'activeColumn' => $column,
            'errorCounts' => $import->meta['error_counts'] ?? [],
        ]);
    }

    public function template(): StreamedResponse
    {
        return $this->exporter->template();
    }

    public function exportErrors(Import $import): StreamedResponse
    {
        abort_if($import->invalid_rows === 0, 404);

        return $this->exporter->errors($import);
    }

    public function destroy(Import $import): RedirectResponse
    {
        if ($import->is_protected) {
            return redirect()->route('imports.index')
                ->with('status', 'El archivo de demostración no se puede eliminar.');
        }

        if ($import->stored_path) {
            Storage::delete($import->stored_path);
        }

        $import->delete(); // borra en cascada sus ventas y errores

        return redirect()->route('imports.index')
            ->with('status', "Se eliminó «{$import->original_name}» y sus ventas.");
    }
}
