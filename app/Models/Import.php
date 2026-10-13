<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_WITH_ERRORS = 'completed_with_errors';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'original_name', 'stored_path', 'status', 'strict',
        'total_rows', 'valid_rows', 'invalid_rows', 'meta', 'finished_at', 'is_protected',
    ];

    protected $casts = [
        'strict' => 'boolean',
        'is_protected' => 'boolean',
        'total_rows' => 'integer',
        'valid_rows' => 'integer',
        'invalid_rows' => 'integer',
        'meta' => 'array',
        'finished_at' => 'datetime',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function rowErrors(): HasMany
    {
        return $this->hasMany(ImportRowError::class);
    }

    /** Porcentaje de filas válidas (0–100). */
    public function successRate(): float
    {
        return $this->total_rows > 0
            ? round($this->valid_rows / $this->total_rows * 100, 1)
            : 0.0;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'Completa',
            self::STATUS_WITH_ERRORS => 'Completa con errores',
            self::STATUS_REJECTED => 'Rechazada',
            self::STATUS_FAILED => 'No se pudo leer',
            default => 'Procesando',
        };
    }

    /** Tono visual de la etiqueta de estado. */
    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'ok',
            self::STATUS_WITH_ERRORS => 'warn',
            self::STATUS_REJECTED, self::STATUS_FAILED => 'bad',
            default => 'neutral',
        };
    }

    /** Filas que realmente se guardaron como ventas. */
    public function savedRows(): int
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_WITH_ERRORS], true)
            ? $this->valid_rows
            : 0;
    }
}
