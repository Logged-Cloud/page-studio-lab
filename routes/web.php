<?php

use Illuminate\Support\Facades\Route;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Support\PageRenderer;

Route::view('/', 'index')->name('home');
Route::view('/route-builder', 'route-builder')->name('route-builder');
Route::view('/variables', 'variables')->name('variables');

Route::get('/routes', function () {
    $routes = RouteDefinition::with('segments.variable')->get();
    $pageRouteIds = Page::pluck('route_id')->all();
    return view('routes-list', compact('routes', 'pageRouteIds'));
})->name('routes.list');

Route::get('/pages/{route}/edit', function (RouteDefinition $route) {
    return view('page-edit', ['route' => $route]);
})->name('pages.edit');

// Mount the editor by pageId only · mirrors how studio.logged.cloud's
// playground binds the page-builder when a visitor picks a demo page.
// Used by Dusk to pin down the "graphs come up empty" bug.
Route::get('/pages-by-id/{page}/edit', function (Page $page) {
    return view('page-edit-by-id', ['page' => $page]);
})->name('pages.edit-by-id');

Route::get('/pages/{route}/preview', function (RouteDefinition $route) {
    $page = Page::where('route_id', $route->id)->first();
    if (! $page) abort(404, 'No page authored for this route yet.');

    $context = $page->previewContext();
    $html = PageRenderer::render((array) $page->blocks, $context);

    return view('page-preview', compact('route', 'page', 'context', 'html'));
})->name('pages.preview');
