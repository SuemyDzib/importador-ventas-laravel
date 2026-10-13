<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

/**
 * Reconoce los encabezados del archivo aunque vengan con acentos, mayúsculas,
 * espacios o sinónimos ("Correo electrónico", "E-mail", "EMAIL" → email).
 */
class HeaderMapper
{
    /** Columna canónica => etiqueta visible y alias aceptados (ya normalizados). */
    public const COLUMNS = [
        'fecha' => [
            'label' => 'Fecha',
            'aliases' => ['fecha', 'fecha_venta', 'fecha_de_venta', 'date', 'dia'],
        ],
        'cliente' => [
            'label' => 'Cliente',
            'aliases' => ['cliente', 'nombre', 'nombre_cliente', 'nombre_del_cliente', 'customer', 'razon_social'],
        ],
        'email' => [
            'label' => 'Email',
            'aliases' => ['email', 'e_mail', 'correo', 'correo_electronico', 'mail'],
        ],
        'producto' => [
            'label' => 'Producto',
            'aliases' => ['producto', 'articulo', 'product', 'descripcion'],
        ],
        'categoria' => [
            'label' => 'Categoría',
            'aliases' => ['categoria', 'category', 'linea', 'familia'],
        ],
        'cantidad' => [
            'label' => 'Cantidad',
            'aliases' => ['cantidad', 'cant', 'qty', 'unidades', 'piezas'],
        ],
        'precio_unitario' => [
            'label' => 'Precio unitario',
            'aliases' => ['precio_unitario', 'precio', 'precio_unit', 'p_unitario', 'unit_price', 'costo_unitario'],
        ],
    ];

    /** Clave especial para errores que afectan a toda la fila (p. ej. duplicados). */
    public const ROW = 'fila';

    public static function normalize(string $header): string
    {
        $ascii = strtolower(Str::ascii(trim($header)));

        return trim(preg_replace('/[^a-z0-9]+/', '_', $ascii), '_');
    }

    public static function label(string $key): string
    {
        return self::COLUMNS[$key]['label'] ?? ($key === self::ROW ? 'Fila completa' : $key);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::COLUMNS);
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array{map: array<int, string>, missing: list<string>, ignored: list<string>}
     */
    public function map(array $headerRow): array
    {
        $lookup = [];
        foreach (self::COLUMNS as $key => $definition) {
            foreach ($definition['aliases'] as $alias) {
                $lookup[$alias] = $key;
            }
        }

        $map = [];
        $ignored = [];

        foreach ($headerRow as $index => $header) {
            if ($header === null || trim((string) $header) === '') {
                continue;
            }

            $key = $lookup[self::normalize((string) $header)] ?? null;

            // Si una columna aparece dos veces, se usa la primera y se ignora la otra.
            if ($key !== null && ! in_array($key, $map, true)) {
                $map[$index] = $key;
            } else {
                $ignored[] = (string) $header;
            }
        }

        $missing = array_values(array_diff(self::keys(), $map));

        return compact('map', 'missing', 'ignored');
    }
}
