<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Page Studio Lab' }}</title>
    <style>
        :root {
            color-scheme: dark;
            --surface: #16171a;
            --surface-2: #1E1F22;
            --line: #3A3D40;
            --ink: #F0EDE5;
            --ink-dim: #A3A099;
            --accent: #2C66E8;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--surface);
            color: var(--ink);
            font-family: -apple-system, system-ui, sans-serif;
            font-size: 14px;
        }
        [x-cloak] { display: none !important; }
        header.lab-bar {
            background: var(--surface-2);
            border-bottom: 1px solid var(--line);
            padding: .75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        header.lab-bar h1 { margin: 0; font-size: 1rem; font-weight: 600; }
        header.lab-bar nav { display: flex; gap: .75rem; margin-left: auto; }
        header.lab-bar a {
            color: var(--ink-dim);
            text-decoration: none;
            padding: .3rem .65rem;
            border-radius: .3rem;
        }
        header.lab-bar a:hover, header.lab-bar a.is-active {
            color: var(--ink);
            background: rgba(255,255,255,.05);
        }
        main { max-width: 60rem; margin: 0 auto; padding: 1.5rem; }
        main h1 { font-size: 1.4rem; margin: 0 0 .65rem; }
        main p.lead { color: var(--ink-dim); margin: 0 0 1.5rem; }
        code, pre { font-family: ui-monospace, monospace; }
    </style>
    @livewireStyles
</head>
<body>
    <header class="lab-bar">
        <h1>page-studio-lab</h1>
        <nav>
            @php $current = request()->route()?->getName(); @endphp
            <a href="{{ route('home') }}" class="{{ $current === 'home' ? 'is-active' : '' }}">Home</a>
            <a href="{{ route('route-builder') }}" class="{{ $current === 'route-builder' ? 'is-active' : '' }}">Route builder</a>
            <a href="{{ route('variables') }}" class="{{ $current === 'variables' ? 'is-active' : '' }}">Variables</a>
            <a href="{{ route('routes.list') }}" class="{{ $current === 'routes.list' ? 'is-active' : '' }}">Saved routes</a>
        </nav>
    </header>
    <main>
        {{ $slot ?? '' }}
        @yield('content')
    </main>
    @livewireScripts
    @stack('scripts')
</body>
</html>
