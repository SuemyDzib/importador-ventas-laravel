<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Importador de ventas')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --paper: #EEF2F0;
            --sheet: #FFFFFF;
            --ink: #17302E;
            --muted: #5B6F6C;
            --grid: #CFDAD6;
            --gutter: #E3EAE7;
            --action: #22577A;
            --action-dark: #183F59;
            --ok: #2E7D5B;
            --ok-tint: #DDF0E6;
            --warn: #8F6210;
            --warn-tint: #F7EBCF;
            --bad: #B23A26;
            --bad-tint: #F8E1DB;
            --radius: 6px;
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0; background: var(--paper); color: var(--ink);
            font: 16px/1.55 "Public Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
            font-variant-numeric: tabular-nums;
        }
        a { color: var(--action); }
        a:hover { color: var(--action-dark); }
        :focus-visible { outline: 3px solid #7FB3D5; outline-offset: 2px; }

        .topbar { background: var(--ink); color: #fff; }
        .topbar .wrap { display: flex; align-items: center; gap: 2rem; min-height: 56px; flex-wrap: wrap; }
        .brand { font-weight: 700; color: #fff; text-decoration: none; letter-spacing: -.01em; }
        .brand span { opacity: .6; font-weight: 400; }
        .nav { display: flex; gap: .25rem; margin-left: auto; }
        .nav a { color: #C9DAD6; text-decoration: none; padding: .45rem .8rem; border-radius: var(--radius); }
        .nav a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .nav a[aria-current="page"] { color: #fff; background: rgba(255,255,255,.14); }

        .wrap { width: min(1180px, 100% - 2.5rem); margin-inline: auto; }
        main.wrap { padding: 2.5rem 0 4rem; }

        h1 { font-size: 2rem; line-height: 1.15; margin: 0 0 .5rem; letter-spacing: -.02em; }
        h2 { font-size: 1.25rem; margin: 0 0 1rem; letter-spacing: -.01em; }
        .lead { color: var(--muted); margin: 0 0 2rem; max-width: 62ch; }
        .muted { color: var(--muted); }
        .small { font-size: .875rem; }

        .panel { background: var(--sheet); border: 1px solid var(--grid); border-radius: var(--radius); padding: 1.5rem; }
        .stack > * + * { margin-top: 2.5rem; }

        .btn {
            display: inline-flex; align-items: center; gap: .5rem; border: 1px solid transparent;
            padding: .6rem 1.1rem; border-radius: var(--radius); font: inherit; font-weight: 600;
            cursor: pointer; text-decoration: none; line-height: 1.2;
        }
        .btn-primary { background: var(--action); color: #fff; }
        .btn-primary:hover { background: var(--action-dark); color: #fff; }
        .btn-quiet { background: transparent; color: var(--action); border-color: var(--grid); }
        .btn-quiet:hover { background: var(--gutter); }
        .btn-danger { background: transparent; color: var(--bad); border: 0; padding: .3rem .4rem; font-weight: 400; }
        .btn-danger:hover { text-decoration: underline; }

        .badge { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .8125rem; font-weight: 600; white-space: nowrap; }
        .badge.ok { background: var(--ok-tint); color: var(--ok); }
        .badge.warn { background: var(--warn-tint); color: var(--warn); }
        .badge.bad { background: var(--bad-tint); color: var(--bad); }
        .badge.neutral { background: var(--gutter); color: var(--muted); }

        .callout { border-left: 4px solid; padding: 1rem 1.25rem; border-radius: 0 var(--radius) var(--radius) 0; }
        .callout p { margin: 0; }
        .callout p + p { margin-top: .35rem; }
        .callout.bad { border-color: var(--bad); background: var(--bad-tint); }
        .callout.warn { border-color: var(--warn); background: var(--warn-tint); }
        .callout.ok { border-color: var(--ok); background: var(--ok-tint); }

        .scroll-x { overflow-x: auto; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th, table.list td { text-align: left; padding: .75rem .9rem; border-bottom: 1px solid var(--grid); vertical-align: middle; }
        table.list th { font-size: .8125rem; font-weight: 600; color: var(--muted); }
        table.list td.num, table.list th.num { text-align: right; }
        table.list tr:last-child td { border-bottom: 0; }

        .pagination { display: flex; gap: .5rem; list-style: none; padding: 0; margin: 1.25rem 0 0; }
        .pagination li > * { display: inline-block; padding: .4rem .8rem; border: 1px solid var(--grid); border-radius: var(--radius); background: var(--sheet); text-decoration: none; }
        .pagination li.disabled > * { color: var(--muted); opacity: .6; }

        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
    </style>
    @stack('styles')
</head>
<body>
    <header class="topbar">
        <div class="wrap">
            <a class="brand" href="{{ route('imports.index') }}">Ventas <span>importador</span></a>
            <nav class="nav" aria-label="Principal">
                <a href="{{ route('imports.index') }}" @if(request()->routeIs('imports.*')) aria-current="page" @endif>Importaciones</a>
                <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Tablero</a>
            </nav>
        </div>
    </header>

    <main class="wrap">
        @if (session('status'))
            <div class="callout ok" role="status" style="margin-bottom:2rem"><p>{{ session('status') }}</p></div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
