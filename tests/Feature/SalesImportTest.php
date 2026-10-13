<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SalesImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function makeXlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'ventas.xlsx', null, null, true);
    }

    public function test_guarda_filas_validas_y_aparta_las_invalidas(): void
    {
        $file = $this->makeXlsx([
            ['Fecha de venta', 'Cliente', 'Correo electrónico', 'Producto', 'Categoría', 'Cantidad', 'Precio'],
            ['15/03/2026', 'Ana López', 'ana@ejemplo.com', 'Teclado', 'electrónica', 2, '1,250.50'],
            ['16/03/2026', '', 'no-es-correo', 'Mouse', 'Electrónica', 0, 199],
            ['15/03/2026', 'Ana López', 'ana@ejemplo.com', 'Teclado', 'Electrónica', 2, 1250.5], // duplicada
        ]);

        $response = $this->post(route('imports.store'), ['archivo' => $file]);

        $import = Import::first();
        $response->assertRedirect(route('imports.show', $import));

        $this->assertSame(Import::STATUS_WITH_ERRORS, $import->status);
        $this->assertSame(3, $import->total_rows);
        $this->assertSame(1, $import->valid_rows);
        $this->assertSame(2, $import->invalid_rows);

        $sale = Sale::first();
        $this->assertSame('Electrónica', $sale->categoria);
        $this->assertEquals(2501.00, (float) $sale->total);

        $badRow = $import->rowErrors()->where('row_number', 3)->first();
        $this->assertArrayHasKey('cliente', $badRow->messages);
        $this->assertArrayHasKey('email', $badRow->messages);
        $this->assertArrayHasKey('cantidad', $badRow->messages);

        $duplicate = $import->rowErrors()->where('row_number', 4)->first();
        $this->assertArrayHasKey('fila', $duplicate->messages);
    }

    public function test_el_modo_estricto_no_guarda_nada_si_hay_errores(): void
    {
        $file = $this->makeXlsx([
            ['Fecha', 'Cliente', 'Email', 'Producto', 'Categoría', 'Cantidad', 'Precio unitario'],
            ['15/03/2026', 'Ana', 'ana@ejemplo.com', 'Teclado', 'Oficina', 1, 100],
            ['15/03/2026', 'Luis', 'luis@', 'Mouse', 'Oficina', 1, 100],
        ]);

        $this->post(route('imports.store'), ['archivo' => $file, 'estricto' => '1']);

        $this->assertSame(Import::STATUS_REJECTED, Import::first()->status);
        $this->assertSame(0, Sale::count());
    }

    public function test_falla_si_faltan_columnas_obligatorias(): void
    {
        $file = $this->makeXlsx([
            ['Fecha', 'Cliente', 'Producto'],
            ['15/03/2026', 'Ana', 'Teclado'],
        ]);

        $this->post(route('imports.store'), ['archivo' => $file]);

        $import = Import::first();
        $this->assertSame(Import::STATUS_FAILED, $import->status);
        $this->assertStringContainsString('Email', $import->meta['error']);
    }

    public function test_rechaza_formatos_no_soportados(): void
    {
        $file = UploadedFile::fake()->create('ventas.pdf', 10);

        $this->post(route('imports.store'), ['archivo' => $file])
            ->assertSessionHasErrors('archivo');

        $this->assertSame(0, Import::count());
    }

    public function test_las_paginas_cargan(): void
    {
        $this->get(route('imports.index'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('imports.template'))->assertOk();
    }

    public function test_las_paginas_cargan_con_datos(): void
    {
        $file = $this->makeXlsx([
            ['Fecha', 'Cliente', 'Email', 'Producto', 'Categoría', 'Cantidad', 'Precio unitario'],
            ['15/03/2026', 'Ana', 'ana@ejemplo.com', 'Teclado', 'Oficina', 1, 100],
            ['15/03/2026', 'Luis', 'luis@', 'Mouse', 'Oficina', 1, 100],
        ]);

        $this->post(route('imports.store'), ['archivo' => $file]);
        $import = Import::first();

        $this->get(route('imports.index'))->assertOk();
        $this->get(route('imports.show', $import))->assertOk()->assertSee('No es un correo válido.');
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('imports.errors', $import))->assertOk();
    }

    public function test_el_archivo_de_demo_no_se_puede_eliminar(): void
    {
        $import = Import::create([
            'original_name' => 'ventas_con_errores.xlsx',
            'status' => Import::STATUS_COMPLETED,
            'is_protected' => true,
        ]);

        $this->delete(route('imports.destroy', $import))
            ->assertRedirect(route('imports.index'));

        $this->assertModelExists($import);
    }
}
