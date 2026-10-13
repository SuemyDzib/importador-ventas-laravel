@extends('layouts.app')

@section('title', 'Importar ventas')

@push('styles')
<style>
    .upload { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 2rem; align-items: start; }
    .drop {
        display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
        min-height: 190px; padding: 1.5rem; border: 2px dashed var(--grid); border-radius: var(--radius);
        background: var(--paper); cursor: pointer; transition: border-color .15s, background .15s;
    }
    .drop:hover, .drop.is-over { border-color: var(--action); background: #E4EDF3; }
    .drop.has-file { border-style: solid; border-color: var(--ok); background: var(--ok-tint); }
    .drop strong { font-size: 1.125rem; }
    .drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .drop:focus-within { outline: 3px solid #7FB3D5; outline-offset: 2px; }
    .check { display: flex; gap: .6rem; align-items: flex-start; margin: 1.25rem 0; }
    .check input { margin-top: .3rem; width: 1.05rem; height: 1.05rem; accent-color: var(--action); }
    .actions { display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; }
    .field-error { color: var(--bad); font-weight: 600; margin: .75rem 0 0; }
    .columns { list-style: none; padding: 0; margin: 0; border: 1px solid var(--grid); border-radius: var(--radius); }
    .columns li { display: grid; grid-template-columns: 2rem 1fr; border-bottom: 1px solid var(--grid); }
    .columns li:last-child { border-bottom: 0; }
    .columns .letter { background: var(--gutter); color: var(--muted); text-align: center; padding: .4rem 0; font-size: .8125rem; border-right: 1px solid var(--grid); }
    .columns .name { padding: .4rem .75rem; }
    .history-head { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; flex-wrap: wrap; }
    @media (max-width: 760px) { .upload { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="stack">
    <section>
        <h1>Importar ventas desde Excel</h1>
        <p class="lead">Sube un archivo y revisamos cada fila antes de guardarla. Las filas con problemas quedan apartadas para que veas exactamente qué corregir.</p>

        <div class="panel upload">
            <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" id="upload-form">
                @csrf
                <label class="drop" id="drop">
                    <input type="file" name="archivo" id="archivo" accept=".xlsx,.xls,.ods,.csv" required>
                    <strong id="drop-title">Arrastra tu archivo aquí</strong>
                    <span class="muted" id="drop-hint">o haz clic para elegirlo · .xlsx, .xls, .ods o .csv, hasta 10 MB</span>
                </label>

                @error('archivo')
                    <p class="field-error" role="alert">{{ $message }}</p>
                @enderror

                <label class="check">
                    <input type="checkbox" name="estricto" value="1" @checked(old('estricto'))>
                    <span>
                        Rechazar el archivo completo si alguna fila tiene errores
                        <span class="muted small" style="display:block">Si no lo marcas, se guardan las filas correctas y se apartan las demás.</span>
                    </span>
                </label>

                <div class="actions">
                    <button type="submit" class="btn btn-primary" id="submit-btn">Importar archivo</button>
                    <a class="btn btn-quiet" href="{{ route('imports.template') }}">Descargar plantilla</a>
                </div>
            </form>

            <div>
                <h2>Columnas que buscamos</h2>
                <ul class="columns">
                    @foreach ($columns as $key => $column)
                        <li><span class="letter">{{ chr(65 + $loop->index) }}</span><span class="name">{{ $column['label'] }}</span></li>
                    @endforeach
                </ul>
                <p class="muted small" style="margin:.75rem 0 0">
                    El orden no importa y reconocemos nombres parecidos: «Correo electrónico» cuenta como Email y «Precio» como Precio unitario.
                    Las fechas pueden venir como fecha de Excel o como texto DD/MM/AAAA.
                </p>
            </div>
        </div>
    </section>

    <section>
        <div class="history-head">
            <h2>Historial</h2>
            @if ($imports->total() > 0)
                <span class="muted small">{{ $imports->total() }} {{ $imports->total() === 1 ? 'archivo' : 'archivos' }}</span>
            @endif
        </div>

        @if ($imports->isEmpty())
            <div class="panel">
                <p style="margin:0">Todavía no has importado ningún archivo. Descarga la plantilla, llénala y súbela arriba.</p>
            </div>
        @else
            <div class="panel scroll-x" style="padding:0">
                <table class="list">
                    <thead>
                        <tr>
                            <th>Archivo</th>
                            <th>Subido</th>
                            <th class="num">Filas</th>
                            <th class="num">Guardadas</th>
                            <th class="num">Con errores</th>
                            <th>Estado</th>
                            <th><span class="sr-only" style="position:absolute;left:-9999px">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($imports as $import)
                            <tr>
                                <td><a href="{{ route('imports.show', $import) }}">{{ $import->original_name }}</a></td>
                                <td class="muted">{{ $import->created_at->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}</td>
                                <td class="num">{{ number_format($import->total_rows) }}</td>
                                <td class="num">{{ number_format($import->savedRows()) }}</td>
                                <td class="num">{{ number_format($import->invalid_rows) }}</td>
                                <td><span class="badge {{ $import->statusTone() }}">{{ $import->statusLabel() }}</span></td>
                                <td class="num">
                                    @if ($import->is_protected)
                                        <span class="badge neutral" title="Archivo de demostración, no se puede eliminar">Demo</span>
                                    @else
                                        <form method="POST" action="{{ route('imports.destroy', $import) }}"
                                            onsubmit="return confirm('¿Eliminar este archivo y las ventas que se guardaron con él?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger" type="submit">Eliminar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $imports->links('partials.pagination') }}
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const drop = document.getElementById('drop');
        const input = document.getElementById('archivo');
        const title = document.getElementById('drop-title');
        const hint = document.getElementById('drop-hint');

        function showFile() {
            const file = input.files[0];
            if (!file) return;
            drop.classList.add('has-file');
            title.textContent = file.name;
            hint.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB · haz clic para cambiarlo';
        }

        input.addEventListener('change', showFile);
        ['dragenter', 'dragover'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.add('is-over'); }));
        ['dragleave', 'drop'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.remove('is-over'); }));
        drop.addEventListener('drop', ev => { input.files = ev.dataTransfer.files; showFile(); });

        document.getElementById('upload-form').addEventListener('submit', function () {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.textContent = 'Revisando filas…';
        });
    })();
</script>
@endpush
