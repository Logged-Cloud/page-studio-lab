@extends('_layout', ['title' => 'Route builder · page-studio-lab'])

@section('content')
    <h1>Route builder</h1>
    <p class="lead">
        Type the URL in the chip editor, then right-click (or long-press on touch)
        any segment to turn it into a variable. Variables persist to the shared
        library and can be reused by other routes.
    </p>

    @livewire('page-studio.route-builder')
@endsection
