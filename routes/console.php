<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use App\Models\Import;
use App\Services\Import\SalesImporter;
use Illuminate\Http\UploadedFile;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:cargar', function (SalesImporter $importer) {
    if (Import::exists()) {
        $this->info('Ya hay datos; no se cargó la demo.');
        return;
    }

    $path = base_path('ejemplos/ventas_con_errores.xlsx');
    $import = $importer->import(new UploadedFile($path, 'ventas_con_errores.xlsx', null, null, true));
    $import->update(['is_protected' => true]);

    $this->info("Demo cargada: {$import->valid_rows} válidas, {$import->invalid_rows} con errores.");
})->purpose('Carga el archivo de ejemplo para la demo');
