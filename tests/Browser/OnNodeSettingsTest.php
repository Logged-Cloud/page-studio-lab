<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Blender-style on-node settings · every settable field renders
 * inside the node card with a wireable socket pip on the left. No
 * right-rail. Wiring an edge to a setting's name overrides the
 * static control · the static row dims out.
 */
class OnNodeSettingsTest extends DuskTestCase
{
    public function test_every_settable_field_renders_a_socket_pip_inside_the_node_card(): void
    {
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::firstOrCreate(
            ['name' => 'dusk.on-node-settings'],
            ['method' => 'GET', 'path_template' => '/dusk-on-node-settings'],
        );
        $page = \LoggedCloud\PageStudio\Models\Page::firstOrCreate(
            ['route_id' => $route->id],
            ['blocks' => [], 'status' => 'draft'],
        );
        \LoggedCloud\PageStudio\Models\NodeGraph::updateOrCreate(
            ['route_id' => $route->id],
            [
                'nodes' => [
                    ['id' => 'br', 'type' => 'image.brightness', 'settings' => ['value' => '1.0'], 'position' => ['x' => 200, 'y' => 200]],
                ],
                'edges' => [],
            ],
        );

        $this->browse(function (Browser $b) use ($route) {
            $b->resize(1440, 900)
                ->visit('/pages/'.$route->id.'/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(800);

            // Open the node drawer so the canvas + nodes render.
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(500);

            $shape = $b->script(<<<'JS'
                const node = document.querySelector('[data-node-id="br"]');
                if (! node) return null;

                // Settings rows live inside the node body · each has a
                // socket pip and a control. Confirm the `value` setting
                // surfaces a wireable pip with the right data-attrs.
                const settingRow = node.querySelector('.ps-ne-setting-row');
                const pip = settingRow ? settingRow.querySelector('.ps-ne-socket') : null;
                const ctrl = settingRow ? settingRow.querySelector('input, select, textarea') : null;
                return {
                    hasSettingRow: !! settingRow,
                    pipKind:       pip ? pip.dataset.socketKind  : null,
                    pipKey:        pip ? pip.dataset.socketKey   : null,
                    pipNode:       pip ? pip.dataset.socketNode  : null,
                    controlTag:    ctrl ? ctrl.tagName.toLowerCase() : null,
                };
            JS)[0];

            $this->assertTrue((bool) ($shape['hasSettingRow'] ?? false),
                'image.brightness should render at least one .ps-ne-setting-row on the node card · got '.json_encode($shape));
            $this->assertSame('in', $shape['pipKind'] ?? null,
                'the pip must declare itself as an input socket · got '.json_encode($shape));
            $this->assertSame('value', $shape['pipKey'] ?? null,
                'the pip key must match the settings field name · got '.json_encode($shape));
            $this->assertSame('br', $shape['pipNode'] ?? null,
                'the pip must address its node id · got '.json_encode($shape));
            $this->assertNotNull($shape['controlTag'] ?? null,
                'the row must include a static control (input/select/textarea) · got '.json_encode($shape));
        });

        \LoggedCloud\PageStudio\Models\NodeGraph::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\Page::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteDefinition::where('id', $route->id)->delete();
    }

    public function test_the_old_right_rail_node_settings_aside_is_gone(): void
    {
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::firstOrCreate(
            ['name' => 'dusk.no-rail'],
            ['method' => 'GET', 'path_template' => '/dusk-no-rail'],
        );
        \LoggedCloud\PageStudio\Models\Page::firstOrCreate(
            ['route_id' => $route->id],
            ['blocks' => [], 'status' => 'draft'],
        );
        \LoggedCloud\PageStudio\Models\NodeGraph::updateOrCreate(
            ['route_id' => $route->id],
            [
                'nodes' => [
                    ['id' => 'br', 'type' => 'image.brightness', 'settings' => ['value' => '1.0'], 'position' => ['x' => 200, 'y' => 200]],
                ],
                'edges' => [],
            ],
        );

        $this->browse(function (Browser $b) use ($route) {
            $b->resize(1440, 900)
                ->visit('/pages/'.$route->id.'/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(500);

            // Select the node so the OLD UX would have surfaced the
            // right-rail. The aside should NOT exist anywhere on the
            // page · settings live on the node now.
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
                wire.set('selectedNodeId', 'br');
            JS);
            $b->pause(600);

            $hasOldAside = $b->script(<<<'JS'
                return !! document.querySelector('aside.ps-ne-settings');
            JS)[0];

            $this->assertFalse((bool) $hasOldAside,
                'the right-rail Node Settings aside must not render · settings now live on the node card');
        });

        \LoggedCloud\PageStudio\Models\NodeGraph::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\Page::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteDefinition::where('id', $route->id)->delete();
    }
}
