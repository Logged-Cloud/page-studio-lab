<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Regression · clicking a node in the Variables Modifier was making
 * the page-builder's RIGHT rail (block settings + comments +
 * activity) flick open. The rail's x-show still tracked
 * $wire.selectedNodeId from before the Blender-style on-node
 * settings refactor, even though node settings now live on the
 * node card and the rail is purely a BLOCK surface.
 */
class NodeSelectionDoesNotOpenBlockRailTest extends DuskTestCase
{
    public function test_selecting_a_node_does_not_open_the_block_rail(): void
    {
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::firstOrCreate(
            ['name' => 'dusk.node-vs-block-rail'],
            ['method' => 'GET', 'path_template' => '/dusk-node-vs-block-rail'],
        );
        \LoggedCloud\PageStudio\Models\Page::firstOrCreate(
            ['route_id' => $route->id],
            ['blocks' => [], 'status' => 'draft'],
        );
        \LoggedCloud\PageStudio\Models\NodeGraph::updateOrCreate(
            ['route_id' => $route->id],
            [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'source.constant', 'settings' => ['value' => 'x'], 'position' => ['x' => 100, 'y' => 100]],
                ],
                'edges' => [],
            ],
        );

        $this->browse(function (Browser $b) use ($route) {
            $b->resize(1440, 900)
                ->visit('/pages/'.$route->id.'/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(700);
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
                wire.set('selectedPath', '');
                wire.set('selectedNodeId', null);
            JS);
            $b->pause(600);

            // Baseline · no selection, right rail should be hidden.
            $base = $b->script(<<<'JS'
                const aside = document.querySelector('aside.ps-pb-rail--right');
                if (! aside) return { exists: false };
                return {
                    exists: true,
                    visible: getComputedStyle(aside).display !== 'none' && aside.offsetWidth > 0,
                };
            JS)[0];

            $this->assertTrue((bool) $base['exists'], 'right rail aside must be in the DOM');
            $this->assertFalse((bool) $base['visible'],
                'with no selection the BLOCK right-rail must be hidden · got '.json_encode($base));

            // Select a NODE and confirm the BLOCK rail stayed hidden.
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).set('selectedNodeId', 'n1');
            JS);
            $b->pause(600);

            $afterNode = $b->script(<<<'JS'
                const aside = document.querySelector('aside.ps-pb-rail--right');
                return {
                    visible: getComputedStyle(aside).display !== 'none' && aside.offsetWidth > 0,
                };
            JS)[0];

            $this->assertFalse((bool) $afterNode['visible'],
                'selecting a node must NOT open the block right-rail · got '.json_encode($afterNode));
        });

        \LoggedCloud\PageStudio\Models\NodeGraph::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\Page::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteDefinition::where('id', $route->id)->delete();
    }
}
