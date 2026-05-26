@extends('_studio_layout', [
    'title' => 'Edit · '.$route->name,
    'subtitle' => $route->path_template,
])

@section('content')
    @livewire('page-studio.page-builder', ['routeId' => $route->id])
@endsection
