<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Reproduces "graphs are showing as empty" · the playground passes
 * pageId to the Livewire mount, but the mount only loaded the
 * NodeGraph when a routeId was supplied. Result: open a demo page
 * in the editor, blocks render, but the Variables Modifier comes up
 * empty even though the graph rows exist in the DB.
 *
 * The fix lives in the package · mount() should resolve the page's
 * route_id and load its graph too. This test pins down the
 * end-to-end expectation: a Page that has a NodeGraph through its
 * route should surface those nodes in the editor's `nodes` property.
 */
class MountByPageIdLoadsNodeGraphTest extends DuskTestCase
{
    public function test_mounting_the_editor_by_pageId_loads_the_route_graph(): void
    {
        // Build a tiny route + page + graph triangle so the test
        // doesn't depend on any specific seed state.
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::create([
            'name'          => 'dusk.graph.demo',
            'method'        => 'GET',
            'path_template' => '/dusk-graph-demo',
        ]);
        \LoggedCloud\PageStudio\Models\RouteSegment::create([
            'route_id'      => $route->id,
            'position'      => 0,
            'kind'          => 'literal',
            'literal_value' => 'dusk-graph-demo',
        ]);
        $page = \LoggedCloud\PageStudio\Models\Page::create([
            'route_id' => $route->id,
            'blocks'   => [
                ['id' => 'b-h', 'type' => 'heading', 'settings' => ['text' => '{{ shouted }}', 'level' => 'h1', 'align' => 'left']],
            ],
            'status'   => 'published',
        ]);
        \LoggedCloud\PageStudio\Models\NodeGraph::create([
            'route_id' => $route->id,
            'nodes' => [
                ['id' => 'src-name', 'type' => 'source.route_variable', 'settings' => ['variable_name' => 'name'],     'position' => ['x' => 0,   'y' => 0]],
                ['id' => 'upper-it', 'type' => 'transform.uppercase',   'settings' => [],                              'position' => ['x' => 320, 'y' => 0]],
                ['id' => 'out-it',   'type' => 'output',                'settings' => ['name' => 'shouted'],           'position' => ['x' => 640, 'y' => 0]],
            ],
            'edges' => [
                ['id' => 'e1', 'from_node' => 'src-name', 'from_socket' => 'value', 'to_node' => 'upper-it', 'to_socket' => 'text'],
                ['id' => 'e2', 'from_node' => 'upper-it', 'from_socket' => 'value', 'to_node' => 'out-it',   'to_socket' => 'value'],
            ],
        ]);

        $this->browse(function (Browser $b) use ($page) {
            // pages-by-id route mounts the editor with only pageId ·
            // mirrors the path studio.logged.cloud's playground takes
            // when binding to a demo page. The default /pages/{route}/edit
            // mounts via routeId and would silently mask this bug.
            $b->resize(1440, 900)
                ->visit('/pages-by-id/'.$page->id.'/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(800);

            // Read what the Livewire component actually has · its
            // `nodes` and `edges` properties should mirror the graph
            // row we just seeded.
            $state = $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                return {
                    nodeCount: (wire.get('nodes') || []).length,
                    edgeCount: (wire.get('edges') || []).length,
                    nodeTypes: (wire.get('nodes') || []).map(n => n.type),
                };
            JS)[0];

            $this->assertSame(3, (int) ($state['nodeCount'] ?? 0),
                'editor mounted by pageId should load the route graph nodes · got '.json_encode($state));
            $this->assertSame(2, (int) ($state['edgeCount'] ?? 0),
                'editor mounted by pageId should load the route graph edges · got '.json_encode($state));
            $this->assertContains('transform.uppercase', $state['nodeTypes'] ?? [],
                'the uppercase transform from the seeded graph should be in the loaded node list');
        });

        // Cleanup so the test is idempotent across runs.
        \LoggedCloud\PageStudio\Models\NodeGraph::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\Page::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteSegment::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteDefinition::where('id', $route->id)->delete();
    }
}
