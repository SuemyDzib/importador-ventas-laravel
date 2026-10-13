<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRowError extends Model
{
    protected $fillable = ['import_id', 'row_number', 'row_values', 'messages'];

    protected $casts = [
        'row_values' => 'array', // ['fecha' => '15/03/2026', 'email' => 'juan@', ...]
        'messages' => 'array',   // ['email' => 'No es un correo válido.', 'fila' => '...']
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function hasErrorIn(string $column): bool
    {
        return isset($this->messages[$column]);
    }
}
