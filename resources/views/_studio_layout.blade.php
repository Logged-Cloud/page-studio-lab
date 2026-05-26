<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Page Studio' }}</title>
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
        html, body { height: 100%; }
        body {
            margin: 0;
            background: var(--surface);
            color: var(--ink);
            font-family: -apple-system, system-ui, sans-serif;
            font-size: 14px;
            overflow: hidden;
        }
        [x-cloak] { display: none !important; }
        header.studio-bar {
            background: var(--surface-2);
            border-bottom: 1px solid var(--line);
            padding: .55rem 1rem;
            display: flex;
            align-items: center;
            gap: .85rem;
            flex-shrink: 0;
            height: 3rem;
        }
        header.studio-bar a.back {
            color: var(--ink-dim);
            text-decoration: none;
            padding: .25rem .55rem;
            border-radius: .3rem;
            font-size: .85rem;
        }
        header.studio-bar a.back:hover {
            color: var(--ink);
            background: rgba(255,255,255,.05);
        }
        header.studio-bar .title {
            font-size: .85rem;
            font-weight: 600;
            color: var(--ink-dim);
        }
        header.studio-bar .right {
            margin-left: auto;
            font-size: .8rem;
            color: var(--ink-dim);
        }
        main.studio-main {
            height: calc(100vh - 3rem);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        code { font-family: ui-monospace, monospace; }
    </style>
    @livewireStyles
</head>
<body>
    <header class="studio-bar">
        <a href="{{ route('routes.list') }}" class="back">← Routes</a>
        <span class="title">{{ $title ?? 'Page Studio' }}</span>
        <span class="right">{{ $subtitle ?? '' }}</span>
    </header>
    <main class="studio-main">
        @yield('content')
    </main>
    @livewireScripts
    @stack('scripts')
</body>
</html>
