<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada archivo subido genera una importación con su resumen.
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('stored_path')->nullable();
            $table->string('status', 30)->default('processing');
            $table->boolean('strict')->default(false);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Filas válidas que se guardaron.
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->string('cliente', 120);
            $table->string('email', 150);
            $table->string('producto', 120);
            $table->string('categoria', 60);
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();

            $table->index('fecha');
            $table->index('categoria');
        });

        // Una fila del Excel con errores = un registro, con sus valores y mensajes por columna.
        Schema::create('import_row_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('row_values');
            $table->json('messages');
            $table->timestamps();

            $table->index(['import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_row_errors');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('imports');
    }
};
