@extends('_layout', ['title' => 'page-studio-lab'])

@section('content')
    <h1>Page Studio · lab</h1>
    <p class="lead">A scratchpad for iterating on the page-studio package one step at a time.</p>

    <h2>Step 1 · Route builder ✓</h2>
    <ul>
        <li><a href="{{ route('route-builder') }}">→ Open the route builder</a></li>
        <li><a href="{{ route('variables') }}">→ Variable library</a></li>
        <li><a href="{{ route('routes.list') }}">→ Saved routes + build pages</a></li>
    </ul>

    <h2>Step 2 · Page builder ✓</h2>
    <p>
        Drag-and-drop block authoring with route variables. Click <strong>Build</strong>
        next to any saved route to start composing.
    </p>
    <ul>
        <li><a href="{{ route('routes.list') }}">→ Pick a route to build</a></li>
    </ul>

    <h2 style="opacity:.5">Step 3 · Node diagram <small>(not started)</small></h2>
    <p style="opacity:.5">
        Visual graph of variables + computed values. Drag nodes into the page
        builder to insert derived data (e.g. `auth.user.name.upper`).
    </p>

    <h2 style="opacity:.5">Step 4 · Custom components <small>(not started)</small></h2>
    <p style="opacity:.5">
        Define new block types right in the studio: name, icon, settings schema,
        render template. Stored alongside the built-in components and available
        from the page-builder palette like everything else.
    </p>
@endsection
