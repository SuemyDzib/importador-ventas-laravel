<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $importId = $request->integer('import') ?: null;

        $sales = Sale::query()->when($importId, fn ($q) => $q->where('import_id', $importId));

        $totals = (clone $sales)->selectRaw('
            COUNT(*) as ventas,
            COALESCE(SUM(total), 0) as ingresos,
            COALESCE(SUM(cantidad), 0) as unidades,
            COUNT(DISTINCT email) as clientes
        ')->first();

        $kpis = [
            'ingresos' => (float) $totals->ingresos,
            'ventas' => (int) $totals->ventas,
            'ticket' => $totals->ventas > 0 ? (float) $totals->ingresos / $totals->ventas : 0,
            'unidades' => (int) $totals->unidades,
            'clientes' => (int) $totals->clientes,
        ];

        $monthExpr = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', fecha)",
            'pgsql' => "to_char(fecha, 'YYYY-MM')",
            default => "DATE_FORMAT(fecha, '%Y-%m')",
        };

        $byMonth = (clone $sales)
            ->selectRaw("{$monthExpr} as mes, SUM(total) as total")
            ->groupBy('mes')->orderBy('mes')
            ->pluck('total', 'mes');

        $byCategory = (clone $sales)
            ->selectRaw('categoria, SUM(total) as total')
            ->groupBy('categoria')->orderByDesc('total')
            ->pluck('total', 'categoria');

        $topProducts = (clone $sales)
            ->selectRaw('producto, SUM(cantidad) as unidades')
            ->groupBy('producto')->orderByDesc('unidades')->limit(5)
            ->pluck('unidades', 'producto');

        $quality = Import::whereNotIn('status', [Import::STATUS_FAILED, Import::STATUS_PROCESSING])
            ->latest()->take(8)->get()->reverse()->values();

        return view('dashboard', [
            'kpis' => $kpis,
            'imports' => Import::whereIn('status', [Import::STATUS_COMPLETED, Import::STATUS_WITH_ERRORS])
                ->latest()->get(['id', 'original_name', 'created_at']),
            'selectedImport' => $importId,
            'charts' => [
                'months' => [
                    'labels' => $byMonth->keys()->map(
                        fn ($m) => Carbon::createFromFormat('!Y-m', $m)->locale('es')->isoFormat('MMM YYYY')
                    )->values(),
                    'values' => $byMonth->values()->map(fn ($v) => round((float) $v, 2)),
                ],
                'categories' => [
                    'labels' => $byCategory->keys(),
                    'values' => $byCategory->values()->map(fn ($v) => round((float) $v, 2)),
                ],
                'products' => [
                    'labels' => $topProducts->keys(),
                    'values' => $topProducts->values()->map(fn ($v) => (int) $v),
                ],
                'quality' => [
                    'labels' => $quality->map(fn ($i) => '#'.$i->id.' '.str($i->original_name)->limit(18)),
                    'valid' => $quality->pluck('valid_rows'),
                    'invalid' => $quality->pluck('invalid_rows'),
                ],
            ],
        ]);
    }
}
