@extends('_studio_layout', [
    'title' => 'Edit (by page id) · '.$page->id,
    'subtitle' => 'Mounted via pageId only · mirrors studio playground',
])

@section('content')
    @livewire('page-studio.page-builder', ['pageId' => $page->id])
@endsection
