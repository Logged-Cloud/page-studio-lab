<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end coverage for the bottom-drawer node editor. All tests target
 * route id 3 (dusk.test) which carries one route variable, `userId`.
 *
 * Each test resets the graph via Livewire so they can run in any order.
 */
class NodeEditorTest extends DuskTestCase
{
    /**
     * Reset the lab's node graph + open the editor in one go · shared by
     * every test in this file.
     */
    protected function fresh(Browser $b): void
    {
        $b->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]')
            ->waitFor('[data-component="page-studio.node-editor"]')
            ->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes', []);");
        $b->pause(300);
        $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('edges', []);");
        $b->pause(300);
    }

    protected function lwCall(Browser $b, string $method, ...$args): void
    {
        $jsonArgs = json_encode($args);
        $b->script(
            "Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).call('$method', ...$jsonArgs);"
        );
    }

    protected function readProp(Browser $b, string $prop): mixed
    {
        return $b->script(
            "return Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).get('$prop');"
        )[0];
    }

    /** @test */
    public function test_connecting_two_sockets_creates_a_wire(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->lwCall($b, 'addNode', 'source.constant');
            $b->pause(300);
            $this->lwCall($b, 'addNode', 'transform.uppercase');
            $b->pause(300);

            $nodes = $this->readProp($b, 'nodes');
            $this->assertCount(2, $nodes);
            $sourceId = $nodes[0]['id'];
            $upperId  = $nodes[1]['id'];

            $this->lwCall($b, 'startConnection',    $sourceId, 'value');
            $b->pause(200);
            $this->lwCall($b, 'completeConnection', $upperId,  'text');
            $b->pause(300);

            $edges = $this->readProp($b, 'edges');
            $this->assertCount(1, $edges);
            $this->assertSame($sourceId, $edges[0]['from_node']);
            $this->assertSame($upperId,  $edges[0]['to_node']);
        });
    }

    /** @test */
    public function test_removing_a_node_wipes_its_wires(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->lwCall($b, 'addNode', 'source.constant');
            $this->lwCall($b, 'addNode', 'transform.uppercase');
            $b->pause(300);
            $nodes = $this->readProp($b, 'nodes');
            $this->lwCall($b, 'startConnection',    $nodes[0]['id'], 'value');
            $this->lwCall($b, 'completeConnection', $nodes[1]['id'], 'text');
            $b->pause(300);
            $this->assertCount(1, $this->readProp($b, 'edges'));

            $this->lwCall($b, 'removeNode', $nodes[0]['id']);
            $b->pause(300);
            $this->assertCount(1, $this->readProp($b, 'nodes'));
            $this->assertCount(0, $this->readProp($b, 'edges'));
        });
    }

    /** @test */
    public function test_tidy_lays_sources_left_of_outputs(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            // Spawn out of order so we can prove tidy actually re-arranges.
            $this->lwCall($b, 'addNode', 'output');
            $this->lwCall($b, 'addNode', 'source.constant');
            $b->pause(300);
            $this->lwCall($b, 'tidy');
            $b->pause(300);

            $nodes  = $this->readProp($b, 'nodes');
            $byType = [];
            foreach ($nodes as $n) $byType[$n['type']] = $n['position']['x'];
            $this->assertLessThan($byType['output'], $byType['source.constant'],
                "Source should sit left of Output after Tidy");
        });
    }

    /** @test */
    public function test_undo_restores_state_after_an_add(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->lwCall($b, 'addNode', 'source.constant');
            $b->pause(300);
            $this->assertCount(1, $this->readProp($b, 'nodes'));

            $this->lwCall($b, 'undo');
            $b->pause(300);
            $this->assertCount(0, $this->readProp($b, 'nodes'));

            $this->lwCall($b, 'redo');
            $b->pause(300);
            $this->assertCount(1, $this->readProp($b, 'nodes'));
        });
    }

    /** @test */
    public function test_mute_makes_a_transform_passthrough(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->lwCall($b, 'addNode', 'source.constant');
            $b->pause(500);
            $nodes = $this->readProp($b, 'nodes');
            $sId = $nodes[0]['id'];
            $b->script("const w = Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')); w.set('nodes.0.settings.value', 'hello');");
            $b->pause(500);

            $this->lwCall($b, 'addNode', 'transform.uppercase');
            $b->pause(500);
            $this->lwCall($b, 'addNode', 'output');
            $b->pause(500);
            $nodes = $this->readProp($b, 'nodes');
            $uId = $nodes[1]['id'];
            $oId = $nodes[2]['id'];
            $b->script("const w = Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')); w.set('nodes.2.settings.name', 'r');");
            $b->pause(600);

            $this->lwCall($b, 'startConnection',    $sId, 'value');
            $b->pause(300);
            $this->lwCall($b, 'completeConnection', $uId, 'text');
            $b->pause(300);
            $this->lwCall($b, 'startConnection',    $uId, 'value');
            $b->pause(300);
            $this->lwCall($b, 'completeConnection', $oId, 'value');
            $b->pause(500);

            // Computed properties aren't shipped to the client snapshot, so
            // read nodes + edges back and re-run the engine here.
            $nodes = $this->readProp($b, 'nodes');
            $edges = $this->readProp($b, 'edges');
            $ctx = \LoggedCloud\PageStudio\Support\NodeGraphEngine::evaluate($nodes, $edges, []);
            $this->assertSame('HELLO', $ctx['r'] ?? null);

            $this->lwCall($b, 'toggleMuted', $uId);
            $b->pause(500);
            $nodes = $this->readProp($b, 'nodes');
            $ctx = \LoggedCloud\PageStudio\Support\NodeGraphEngine::evaluate($nodes, $edges, []);
            $this->assertSame('hello', $ctx['r'] ?? null);
        });
    }

    /** @test */
    public function test_duplicate_node_creates_a_sibling_at_an_offset(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->lwCall($b, 'addNode', 'source.constant', 50, 50);
            $b->pause(300);
            $orig = $this->readProp($b, 'nodes')[0];

            $this->lwCall($b, 'duplicateNode', $orig['id']);
            $b->pause(300);
            $nodes = $this->readProp($b, 'nodes');
            $this->assertCount(2, $nodes);
            $this->assertNotSame($orig['id'], $nodes[1]['id']);
            $this->assertSame($orig['type'], $nodes[1]['type']);
            $this->assertSame(80, $nodes[1]['position']['x']);  // 50 + 30
            $this->assertSame(80, $nodes[1]['position']['y']);
        });
    }

    /** @test */
    public function test_copy_paste_via_method_clones_subgraph_with_remapped_edges(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            // Manually pass a clipboard payload to pasteSubgraph so we don't
            // depend on the keyboard hijack in headless Chromium.
            $clip = [
                'nodes' => [
                    ['id' => 'oa', 'type' => 'source.constant',     'settings' => ['value' => 'x'], 'position' => ['x' => 10, 'y' => 10]],
                    ['id' => 'ob', 'type' => 'transform.uppercase', 'settings' => [],              'position' => ['x' => 200, 'y' => 10]],
                ],
                'edges' => [
                    ['from_node' => 'oa', 'from_socket' => 'value', 'to_node' => 'ob', 'to_socket' => 'text'],
                ],
            ];
            $b->script(
                "const w = Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id'));".
                "w.call('pasteSubgraph', ".json_encode($clip['nodes']).", ".json_encode($clip['edges']).", 0, 0);"
            );
            $b->pause(400);

            $nodes = $this->readProp($b, 'nodes');
            $edges = $this->readProp($b, 'edges');
            $this->assertCount(2, $nodes);
            $this->assertCount(1, $edges);
            // Ids are rewritten so the paste doesn't collide with the source.
            $this->assertNotSame('oa', $nodes[0]['id']);
            $this->assertSame($nodes[0]['id'], $edges[0]['from_node']);
        });
    }

    /** @test */
    public function test_wire_bend_persists_per_edge(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->lwCall($b, 'addNode', 'source.constant');
            $this->lwCall($b, 'addNode', 'output');
            $b->pause(300);
            $nodes = $this->readProp($b, 'nodes');
            $this->lwCall($b, 'startConnection',    $nodes[0]['id'], 'value');
            $this->lwCall($b, 'completeConnection', $nodes[1]['id'], 'value');
            $b->pause(300);

            $edge = $this->readProp($b, 'edges')[0];
            $this->lwCall($b, 'bendEdge', $edge['id'], 250, 100);
            $b->pause(300);
            $edges = $this->readProp($b, 'edges');
            $this->assertSame(['x' => 250, 'y' => 100], $edges[0]['bend']);

            // Clearing the bend drops it from the edge entirely.
            $this->lwCall($b, 'bendEdge', $edge['id'], null, null);
            $b->pause(300);
            $edges = $this->readProp($b, 'edges');
            $this->assertArrayNotHasKey('bend', $edges[0]);
        });
    }

    /** @test */
    public function test_image_pipeline_output_flows_through_variable_context(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->lwCall($b, 'addNode', 'image.source');
            $b->pause(500);
            $this->lwCall($b, 'addNode', 'image.grayscale');
            $b->pause(500);
            $this->lwCall($b, 'addNode', 'output');
            $b->pause(500);
            $nodes = $this->readProp($b, 'nodes');

            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes.0.settings.url', 'https://example.test/i.png');");
            $b->pause(500);
            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes.1.settings.value', '1');");
            $b->pause(500);
            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes.2.settings.name', 'avatar');");
            $b->pause(700);

            $this->lwCall($b, 'startConnection',    $nodes[0]['id'], 'image');
            $b->pause(300);
            $this->lwCall($b, 'completeConnection', $nodes[1]['id'], 'image');
            $b->pause(300);
            $this->lwCall($b, 'startConnection',    $nodes[1]['id'], 'image');
            $b->pause(300);
            $this->lwCall($b, 'completeConnection', $nodes[2]['id'], 'value');
            $b->pause(500);

            // Computed `variableContext` isn't on the client snapshot, so
            // pull nodes + edges back and evaluate the engine here.
            $nodes = $this->readProp($b, 'nodes');
            $edges = $this->readProp($b, 'edges');
            $ctx = \LoggedCloud\PageStudio\Support\NodeGraphEngine::evaluate($nodes, $edges, []);
            $this->assertIsArray($ctx['avatar'] ?? null,
                'avatar should be an image array · context dump: '.json_encode($ctx));
            $this->assertSame('https://example.test/i.png', $ctx['avatar']['url']);
            $this->assertSame('grayscale(1)', $ctx['avatar']['filter']);
        });
    }

    /** @test */
    public function test_autosave_persists_to_the_node_graph_row(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->lwCall($b, 'addNode', 'source.constant', 50, 50);
            // Autosave is debounced ~600ms; wait through it.
            $b->pause(1100);

            // Persisted on the route 3 NodeGraph row.
            $stored = \LoggedCloud\PageStudio\Models\NodeGraph::where('route_id', 3)->first();
            $this->assertNotNull($stored);
            $this->assertNotEmpty($stored->nodes);
        });
    }

    /** @test */
    public function test_marquee_select_then_delete_removes_only_the_selected_nodes(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->lwCall($b, 'addNode', 'source.constant',     50,  50);
            $this->lwCall($b, 'addNode', 'transform.uppercase', 250, 50);
            $this->lwCall($b, 'addNode', 'output',              450, 50);
            $b->pause(400);
            $this->assertCount(3, $this->readProp($b, 'nodes'));
            $ids = array_column($this->readProp($b, 'nodes'), 'id');

            // removeNodes is what the Delete-key handler calls; we exercise
            // it directly so we don't depend on synthetic Delete events.
            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).call('removeNodes', ".json_encode([$ids[0], $ids[1]]).");");
            $b->pause(400);
            $remaining = $this->readProp($b, 'nodes');
            $this->assertCount(1, $remaining);
            $this->assertSame($ids[2], $remaining[0]['id']);
        });
    }
    /** @test */
    public function test_creating_a_custom_node_makes_it_evaluable_in_a_graph(): void
    {
        // The lab's `app/PageStudio/Nodes/DuskGreetingNode.php` registers a
        // code-defined node via NodeRegistry on boot · here we just verify
        // that the engine resolves it through the registry path and the
        // wire-up roundtrips through Livewire.

        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->lwCall($b, 'addNode', 'source.constant');
            $b->pause(500);
            $this->lwCall($b, 'addNode', 'custom.dusk_greeting');
            $b->pause(500);
            $this->lwCall($b, 'addNode', 'output');
            $b->pause(500);

            $nodes = $this->readProp($b, 'nodes');
            // Source value = Charles, custom prefix = Howdy, output named r.
            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes.0.settings.value', 'Charles');");
            $b->pause(400);
            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes.1.settings.prefix', 'Howdy');");
            $b->pause(400);
            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes.2.settings.name', 'r');");
            $b->pause(500);

            $this->lwCall($b, 'startConnection',    $nodes[0]['id'], 'value');
            $b->pause(300);
            $this->lwCall($b, 'completeConnection', $nodes[1]['id'], 'who');
            $b->pause(300);
            $this->lwCall($b, 'startConnection',    $nodes[1]['id'], 'value');
            $b->pause(300);
            $this->lwCall($b, 'completeConnection', $nodes[2]['id'], 'value');
            $b->pause(500);

            $nodes = $this->readProp($b, 'nodes');
            $edges = $this->readProp($b, 'edges');
            $ctx = \LoggedCloud\PageStudio\Support\NodeGraphEngine::evaluate($nodes, $edges, []);
            $this->assertSame('Howdy, Charles!', $ctx['r'] ?? null);
        });
    }

    /**
     * Regression · Livewire's morphdom pass used to wipe the SVG path's `d`
     * attribute after `saveGraph()` because the wire path is painted by
     * Alpine, not the server. The package now broadcasts `ps-ne:morphed`
     * after each morph so each canvas re-paints. We assert the `d` survives
     * a saveGraph round-trip.
     *
     * @test
     */
    public function test_wires_survive_autosave_round_trip(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->lwCall($b, 'addNode', 'source.constant');
            $b->pause(300);
            $this->lwCall($b, 'addNode', 'transform.uppercase');
            $b->pause(300);

            $nodes = $this->readProp($b, 'nodes');
            $this->assertCount(2, $nodes);
            $sourceId = $nodes[0]['id'];
            $upperId  = $nodes[1]['id'];

            $this->lwCall($b, 'startConnection',    $sourceId, 'value');
            $b->pause(200);
            $this->lwCall($b, 'completeConnection', $upperId,  'text');
            $b->pause(400);

            // Wire is painted by Alpine after the next animation frame · give
            // it a beat before we sample the attribute.
            $b->pause(400);
            $initial = $b->script("return document.querySelector('.ps-ne-wire')?.getAttribute('d') || '';")[0];
            $this->assertNotEmpty($initial, 'Wire `d` attribute should be set after completeConnection');

            // Force a Livewire round-trip · this fires the `morphed` hook the
            // bug was tied to.
            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).call('saveGraph');");
            $b->pause(1500);

            $after = $b->script("return document.querySelector('.ps-ne-wire')?.getAttribute('d') || '';")[0];
            $this->assertNotEmpty(
                $after,
                'Wire `d` attribute should still be set after saveGraph + morphdom · was: '.var_export($after, true)
            );
        });
    }

    /**
     * Regression · the palette aside and canvas-wrap live in sibling Alpine
     * scopes, so the drop handler can no longer read a local JS variable.
     * The fix shuttles the type through `dataTransfer.setData(...)` with a
     * `ps-ne-palette:` prefix. We dispatch synthetic DragEvents because
     * Dusk's native drag helpers don't cross Alpine scopes cleanly.
     *
     * @test
     */
    public function test_node_palette_drag_drops_a_new_node_on_the_canvas(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->pause(300);
            $this->assertCount(0, $this->readProp($b, 'nodes'));

            $b->script(<<<'JS'
                const dt = new DataTransfer();
                dt.setData('text/plain', 'ps-ne-palette:source.constant');
                const canvas = document.querySelector('.ps-ne-canvas-wrap');
                ['dragstart','dragenter','dragover','drop'].forEach(t => {
                    canvas.dispatchEvent(new DragEvent(t, {
                        dataTransfer: dt,
                        bubbles: true,
                        cancelable: true,
                        clientX: 200,
                        clientY: 200,
                    }));
                });
            JS);
            $b->pause(700);

            $nodes = $this->readProp($b, 'nodes');
            $this->assertNotEmpty($nodes, 'Drop on .ps-ne-canvas-wrap should have added a node');
            $types = array_column($nodes, 'type');
            $this->assertContains(
                'source.constant',
                $types,
                'Palette drag-drop should add a source.constant node · got: '.json_encode($types)
            );
        });
    }
}
