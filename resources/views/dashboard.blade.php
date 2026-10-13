@extends('layouts.app')

@section('title', 'Tablero de ventas')

@push('styles')
<style>
    .head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem; }
    .head h1 { margin: 0; }
    .filter label { display: block; font-size: .875rem; color: var(--muted); margin-bottom: .25rem; }
    .filter select { font: inherit; padding: .5rem .75rem; border: 1px solid var(--grid); border-radius: var(--radius); background: var(--sheet); color: var(--ink); min-width: 260px; max-width: 100%; }

    .kpis {
        display: grid; grid-template-columns: minmax(0, 1.4fr) repeat(2, minmax(0, 1fr));
        gap: 1px; background: var(--grid); border: 1px solid var(--grid);
        border-radius: var(--radius); overflow: hidden; margin: 0;
    }
    .kpis div { background: var(--sheet); padding: 1.25rem 1.5rem; min-width: 0; }
    .kpis dt { color: var(--muted); font-size: .875rem; }
    .kpis dd { margin: .2rem 0 0; font-size: 1.625rem; font-weight: 700; letter-spacing: -.02em; overflow-wrap: anywhere; }
    .kpis div:first-child { grid-row: span 2; display: flex; flex-direction: column; justify-content: center; }
    .kpis div:first-child dd { font-size: clamp(2rem, 4vw, 3rem); color: var(--action); }

    .charts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; }
    .charts .wide { grid-column: 1 / -1; }
    .chart-box { position: relative; height: 280px; }
    .wide .chart-box { height: 300px; }

    @media (max-width: 960px) {
        .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .kpis div:first-child { grid-row: auto; grid-column: 1 / -1; }
    }
    @media (max-width: 760px) { .charts { grid-template-columns: 1fr; } }
    @media (max-width: 420px) {
        .kpis div { padding: 1rem; }
        .kpis dd { font-size: 1.375rem; }
    }
</style>
@endpush

@section('content')
<div class="head">
    <div>
        <h1>Tablero de ventas</h1>
        <p class="muted" style="margin:.4rem 0 0">Solo incluye filas que pasaron la validación.</p>
    </div>

    @if ($imports->isNotEmpty())
        <form class="filter" method="GET" action="{{ route('dashboard') }}">
            <label for="import">Archivo</label>
            <select id="import" name="import" onchange="this.form.submit()">
                <option value="">Todas las importaciones</option>
                @foreach ($imports as $import)
                    <option value="{{ $import->id }}" @selected($selectedImport === $import->id)>
                        {{ $import->original_name }} ({{ $import->created_at->format('d/m/Y') }})
                    </option>
                @endforeach
            </select>
            <noscript><button class="btn btn-quiet" type="submit">Aplicar</button></noscript>
        </form>
    @endif
</div>

@if ($kpis['ventas'] === 0)
    <div class="panel">
        <h2>Aún no hay ventas para mostrar</h2>
        <p style="margin:0">Importa un archivo y las filas válidas aparecerán aquí. <a href="{{ route('imports.index') }}">Ir a importaciones</a></p>
    </div>
@else
    <div class="stack">
        <dl class="kpis">
            <div><dt>Ingresos</dt><dd>${{ number_format($kpis['ingresos'], 2) }}</dd></div>
            <div><dt>Ventas</dt><dd>{{ number_format($kpis['ventas']) }}</dd></div>
            <div><dt>Ticket promedio</dt><dd>${{ number_format($kpis['ticket'], 2) }}</dd></div>
            <div><dt>Unidades</dt><dd>{{ number_format($kpis['unidades']) }}</dd></div>
            <div><dt>Clientes únicos</dt><dd>{{ number_format($kpis['clientes']) }}</dd></div>
        </dl>

        <div class="charts">
            <section class="panel wide">
                <h2>Ingresos por mes</h2>
                <div class="chart-box"><canvas id="chart-months" aria-label="Ingresos por mes" role="img"></canvas></div>
            </section>
            <section class="panel">
                <h2>Ingresos por categoría</h2>
                <div class="chart-box"><canvas id="chart-categories" aria-label="Ingresos por categoría" role="img"></canvas></div>
            </section>
            <section class="panel">
                <h2>Productos más vendidos</h2>
                <div class="chart-box"><canvas id="chart-products" aria-label="Cinco productos con más unidades vendidas" role="img"></canvas></div>
            </section>
            <section class="panel wide">
                <h2>Calidad de los últimos archivos</h2>
                <p class="muted small" style="margin:-.5rem 0 1rem">Filas válidas frente a filas con errores en cada importación.</p>
                <div class="chart-box"><canvas id="chart-quality" aria-label="Filas válidas y con errores por importación" role="img"></canvas></div>
            </section>
        </div>
    </div>
@endif
@endsection

@if ($kpis['ventas'] > 0)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const data = @json($charts);
    const css = getComputedStyle(document.documentElement);
    const c = name => css.getPropertyValue(name).trim();
    const money = v => '$' + Number(v).toLocaleString('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    Chart.defaults.font.family = '"Public Sans", system-ui, sans-serif';
    Chart.defaults.color = c('--muted');
    Chart.defaults.borderColor = c('--grid');
    Chart.defaults.maintainAspectRatio = false;
    if (reduced) Chart.defaults.animation = false;

    new Chart(document.getElementById('chart-months'), {
        type: 'line',
        data: { labels: data.months.labels, datasets: [{
            label: 'Ingresos', data: data.months.values, borderColor: c('--action'),
            backgroundColor: 'rgba(34, 87, 122, .12)', fill: true, tension: .25, pointRadius: 4,
        }]},
        options: {
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => money(ctx.parsed.y) } } },
            scales: { y: { beginAtZero: true, ticks: { callback: money } }, x: { grid: { display: false } } },
        },
    });

    new Chart(document.getElementById('chart-categories'), {
        type: 'bar',
        data: { labels: data.categories.labels, datasets: [{ data: data.categories.values, backgroundColor: c('--action'), borderRadius: 3 }] },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => money(ctx.parsed.x) } } },
            scales: { x: { beginAtZero: true, ticks: { callback: money } }, y: { grid: { display: false } } },
        },
    });

    new Chart(document.getElementById('chart-products'), {
        type: 'bar',
        data: { labels: data.products.labels, datasets: [{ label: 'Unidades', data: data.products.values, backgroundColor: c('--ink'), borderRadius: 3 }] },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } },
        },
    });

    new Chart(document.getElementById('chart-quality'), {
        type: 'bar',
        data: { labels: data.quality.labels, datasets: [
            { label: 'Válidas', data: data.quality.valid, backgroundColor: c('--ok'), borderRadius: 2 },
            { label: 'Con errores', data: data.quality.invalid, backgroundColor: c('--bad'), borderRadius: 2 },
        ]},
        options: {
            plugins: { legend: { position: 'bottom', align: 'start' } },
            scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
</script>
@endpush
@endif
