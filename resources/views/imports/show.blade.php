@extends('layouts.app')

@section('title', $import->original_name)

@push('styles')
<style>
    .back { display: inline-block; margin-bottom: 1rem; text-decoration: none; }
    .title-row { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
    .title-row h1 { margin: 0; word-break: break-word; }
    .meta { color: var(--muted); margin: .5rem 0 2rem; }

    /* Resumen */
    .summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border: 1px solid var(--grid); border-radius: var(--radius); background: var(--sheet); }
    .summary div { padding: 1.25rem 1.5rem; border-right: 1px solid var(--grid); }
    .summary div:last-child { border-right: 0; }
    .summary dt { color: var(--muted); font-size: .875rem; }
    .summary dd { margin: .2rem 0 0; font-size: 2rem; font-weight: 700; letter-spacing: -.02em; }
    .summary .is-ok dd { color: var(--ok); }
    .summary .is-bad dd { color: var(--bad); }
    .ratio { display: flex; height: 10px; border-radius: 999px; overflow: hidden; background: var(--gutter); margin-top: 1rem; }
    .ratio .ok { background: var(--ok); }
    .ratio .bad { background: var(--bad); }
    .ratio-legend { display: flex; justify-content: space-between; font-size: .875rem; color: var(--muted); margin-top: .4rem; }

    /* Filtros */
    .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .chips { display: flex; gap: .4rem; flex-wrap: wrap; }
    .chip { padding: .3rem .75rem; border: 1px solid var(--grid); border-radius: 999px; background: var(--sheet); text-decoration: none; color: var(--ink); font-size: .875rem; }
    .chip:hover { border-color: var(--action); color: var(--action); }
    .chip[aria-current="true"] { background: var(--ink); border-color: var(--ink); color: #fff; }
    .chip b { font-weight: 600; margin-left: .25rem; opacity: .75; }

    /* La hoja: réplica de Excel con las celdas problemáticas marcadas */
    .sheet { border: 1px solid var(--grid); border-radius: var(--radius); background: var(--sheet); }
    .sheet table { border-collapse: collapse; width: 100%; min-width: 980px; font-size: .9375rem; }
    .sheet th, .sheet td { border-right: 1px solid var(--grid); border-bottom: 1px solid var(--grid); }
    .sheet th:last-child, .sheet td:last-child { border-right: 0; }
    .sheet thead .letters th { background: var(--gutter); color: var(--muted); font-weight: 400; font-size: .75rem; padding: .2rem; text-align: center; }
    .sheet thead .labels th { background: var(--sheet); text-align: left; padding: .55rem .7rem; font-weight: 600; font-size: .8125rem; }
    .sheet .gutter { background: var(--gutter); color: var(--muted); text-align: center; width: 3.25rem; font-size: .8125rem; padding: .55rem .25rem; vertical-align: top; }
    .sheet td.cell { padding: .55rem .7rem; vertical-align: top; max-width: 220px; overflow-wrap: anywhere; }
    .sheet td.cell.has-error { background: var(--bad-tint); box-shadow: inset 0 0 0 2px var(--bad); }
    .sheet .value { display: block; }
    .sheet .empty { color: var(--muted); font-style: italic; }
    .sheet .msg { display: block; color: var(--bad); font-size: .8125rem; font-weight: 600; margin-top: .2rem; line-height: 1.35; }
    .sheet tr.row-note td { background: var(--warn-tint); color: var(--warn); font-size: .875rem; font-weight: 600; padding: .4rem .7rem; }

    @media (max-width: 640px) {
        .summary { grid-template-columns: 1fr; }
        .summary div { border-right: 0; border-bottom: 1px solid var(--grid); }
        .summary div:last-child { border-bottom: 0; }
    }
</style>
@endpush

@section('content')
@php
    $meta = $import->meta ?? [];
    $keys = array_keys($columns);
@endphp

<a class="back" href="{{ route('imports.index') }}">← Todas las importaciones</a>

<div class="title-row">
    <h1>{{ $import->original_name }}</h1>
    <span class="badge {{ $import->statusTone() }}">{{ $import->statusLabel() }}</span>
</div>
<p class="meta">
    Subido el {{ $import->created_at->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm') }}
    @if ($import->strict) · modo estricto @endif
</p>

<div class="stack">
    @if ($import->status === \App\Models\Import::STATUS_FAILED)
        <div class="callout bad" role="alert">
            <p><strong>{{ $meta['error'] ?? 'No se pudo procesar el archivo.' }}</strong></p>
            <p>Compara tus encabezados con la <a href="{{ route('imports.template') }}">plantilla</a> y vuelve a <a href="{{ route('imports.index') }}">subir el archivo</a>.</p>
        </div>
    @else
        <section>
            <dl class="summary" style="margin:0">
                <div><dt>Filas leídas</dt><dd>{{ number_format($import->total_rows) }}</dd></div>
                <div class="is-ok"><dt>{{ $import->status === \App\Models\Import::STATUS_REJECTED ? 'Válidas (no guardadas)' : 'Guardadas' }}</dt><dd>{{ number_format($import->valid_rows) }}</dd></div>
                <div class="is-bad"><dt>Con errores</dt><dd>{{ number_format($import->invalid_rows) }}</dd></div>
            </dl>
            <div class="ratio" role="img" aria-label="{{ $import->successRate() }}% de filas válidas">
                <span class="ok" style="width: {{ $import->successRate() }}%"></span>
                <span class="bad" style="width: {{ 100 - $import->successRate() }}%"></span>
            </div>
            <div class="ratio-legend">
                <span>{{ $import->successRate() }}% válidas</span>
                @if ($import->savedRows() > 0)
                    <a href="{{ route('dashboard', ['import' => $import->id]) }}">Ver estas ventas en el tablero</a>
                @endif
            </div>
        </section>

        @if ($import->status === \App\Models\Import::STATUS_REJECTED)
            <div class="callout bad">
                <p><strong>No se guardó ninguna venta.</strong> El modo estricto rechaza el archivo completo cuando hay errores.</p>
                <p>Descarga las filas con errores, corrígelas en tu archivo original y súbelo de nuevo.</p>
            </div>
        @elseif ($import->invalid_rows === 0)
            <div class="callout ok"><p><strong>Todas las filas son válidas</strong> y ya están en el tablero.</p></div>
        @endif

        @if (! empty($meta['ignored_columns']))
            <div class="callout warn">
                <p>Ignoramos {{ count($meta['ignored_columns']) === 1 ? 'esta columna porque no la reconocimos' : 'estas columnas porque no las reconocimos' }}:
                    <strong>{{ implode(', ', $meta['ignored_columns']) }}</strong>.</p>
            </div>
        @endif

        @if ($import->invalid_rows > 0)
            <section>
                <h2>Filas con errores</h2>

                <div class="toolbar">
                    <nav class="chips" aria-label="Filtrar por columna">
                        <a class="chip" href="{{ route('imports.show', $import) }}" @if(! $activeColumn) aria-current="true" @endif>
                            Todas <b>{{ number_format($import->invalid_rows) }}</b>
                        </a>
                        @foreach ($errorCounts as $column => $count)
                            <a class="chip" href="{{ route('imports.show', [$import, 'columna' => $column]) }}" @if($activeColumn === $column) aria-current="true" @endif>
                                {{ \App\Services\Import\HeaderMapper::label($column) }} <b>{{ number_format($count) }}</b>
                            </a>
                        @endforeach
                    </nav>
                    <a class="btn btn-quiet" href="{{ route('imports.errors', $import) }}">Descargar errores en Excel</a>
                </div>

                <div class="sheet scroll-x">
                    <table>
                        <thead>
                            <tr class="letters">
                                <th aria-hidden="true"></th>
                                @foreach ($keys as $key)
                                    <th aria-hidden="true">{{ chr(65 + $loop->index) }}</th>
                                @endforeach
                            </tr>
                            <tr class="labels">
                                <th class="gutter" scope="col">Fila</th>
                                @foreach ($columns as $column)
                                    <th scope="col">{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($failedRows as $row)
                                <tr>
                                    <th class="gutter" scope="row">{{ $row->row_number }}</th>
                                    @foreach ($keys as $key)
                                        @php $value = $row->row_values[$key] ?? null; @endphp
                                        <td class="cell {{ $row->hasErrorIn($key) ? 'has-error' : '' }}">
                                            @if ($value === null || $value === '')
                                                <span class="value empty">vacía</span>
                                            @else
                                                <span class="value">{{ $value }}</span>
                                            @endif
                                            @if ($row->hasErrorIn($key))
                                                <span class="msg">{{ $row->messages[$key] }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                                @if ($row->hasErrorIn(\App\Services\Import\HeaderMapper::ROW))
                                    <tr class="row-note">
                                        <td class="gutter"></td>
                                        <td colspan="{{ count($keys) }}">{{ $row->messages[\App\Services\Import\HeaderMapper::ROW] }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="muted small" style="margin:.75rem 0 0">
                    Mostrando filas {{ $failedRows->firstItem() }}–{{ $failedRows->lastItem() }} de {{ number_format($failedRows->total()) }}.
                    El número de fila corresponde al de tu archivo original.
                </p>
                {{ $failedRows->links('partials.pagination') }}
            </section>
        @endif
    @endif
</div>
@endsection
