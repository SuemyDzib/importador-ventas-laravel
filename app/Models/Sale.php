<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = [
        'import_id', 'fecha', 'cliente', 'email', 'producto',
        'categoria', 'cantidad', 'precio_unitario', 'total',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
