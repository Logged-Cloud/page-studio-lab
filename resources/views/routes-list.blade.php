@extends('_layout', ['title' => 'Saved routes · page-studio-lab'])

@section('content')
    <h1>Saved routes</h1>

    @if ($routes->isEmpty())
        <p class="lead">No routes saved yet. <a href="{{ route('route-builder') }}">Build one</a>.</p>
    @else
        <table style="width:100%;border-collapse:collapse;font-size:.9rem">
            <thead>
                <tr>
                    <th style="text-align:left;padding:.55rem;border-bottom:1px solid var(--line)">Method</th>
                    <th style="text-align:left;padding:.55rem;border-bottom:1px solid var(--line)">Name</th>
                    <th style="text-align:left;padding:.55rem;border-bottom:1px solid var(--line)">Template</th>
                    <th style="text-align:left;padding:.55rem;border-bottom:1px solid var(--line)">Variables</th>
                    <th style="text-align:left;padding:.55rem;border-bottom:1px solid var(--line)">Page</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($routes as $r)
                    <tr>
                        <td style="padding:.55rem;border-bottom:1px solid var(--line)"><code>{{ $r->method }}</code></td>
                        <td style="padding:.55rem;border-bottom:1px solid var(--line)">{{ $r->name }}</td>
                        <td style="padding:.55rem;border-bottom:1px solid var(--line)"><code>{{ $r->path_template }}</code></td>
                        <td style="padding:.55rem;border-bottom:1px solid var(--line)">
                            @foreach ($r->segments->where('kind', 'variable') as $s)
                                <code>{{ '{'.($s->variable->name ?? '?').'}' }}</code>
                            @endforeach
                        </td>
                        <td style="padding:.55rem;border-bottom:1px solid var(--line)">
                            <a href="{{ route('pages.edit', $r) }}">{{ in_array($r->id, $pageRouteIds) ? 'Edit' : 'Build' }}</a>
                            @if (in_array($r->id, $pageRouteIds))
                                · <a href="{{ route('pages.preview', $r) }}">Preview</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
