@extends('_layout', ['title' => 'Variables · page-studio-lab'])

@section('content')
    <h1>Variable library</h1>
    <p class="lead">All variables defined while building routes. Delete is blocked while a variable is still wired into a route.</p>

    @livewire('page-studio.variable-library')
@endsection
