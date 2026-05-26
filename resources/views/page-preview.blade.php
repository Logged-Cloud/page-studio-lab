@extends('_layout', ['title' => 'Preview · '.$route->name])

@section('content')
    <h1>Preview · {{ $route->name }}</h1>
    <p class="lead">
        Rendering <code>{{ $route->path_template }}</code> with sample variable
        values from the variable library.
        · <a href="{{ route('pages.edit', $route) }}">← Edit</a>
    </p>

    <div style="background:#fff;color:#111;padding:2rem;border-radius:.5rem;margin:1rem 0;max-width:48rem">
        {!! $html !!}
    </div>

    <details style="margin-top:1.5rem">
        <summary style="cursor:pointer;color:var(--ink-dim, #A3A099);font-size:.85rem">Variable context used</summary>
        <pre style="background:rgba(0,0,0,.25);padding:.75rem;border-radius:.35rem;font-size:.8rem;margin-top:.5rem">{{ json_encode($context, JSON_PRETTY_PRINT) }}</pre>
    </details>
@endsection
