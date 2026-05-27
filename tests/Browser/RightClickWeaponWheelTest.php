<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * GTA-style weapon-wheel right-click menu on the node canvas.
 *
 * Stage 1 · a donut of category slices (source / transform /
 *           image / convert / output / note + variables).
 * Stage 2 · clicking a slice expands into a list of that
 *           category's node types.
 *
 * Pinning: open the canvas, dispatch a contextmenu event, assert
 * the wheel renders, click a slice, assert the panel switches to
 * the corresponding category, click an item, assert the engine
 * received the addNode call (a new node lands in $wire.nodes).
 */
class RightClickWeaponWheelTest extends DuskTestCase
{
    public function test_right_click_opens_wheel_then_expands_then_drops_a_node(): void
    {
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::firstOrCreate(
            ['name' => 'dusk.weapon-wheel'],
            ['method' => 'GET', 'path_template' => '/dusk-weapon-wheel'],
        );
        \LoggedCloud\PageStudio\Models\Page::firstOrCreate(
            ['route_id' => $route->id],
            ['blocks' => [], 'status' => 'draft'],
        );
        \LoggedCloud\PageStudio\Models\NodeGraph::updateOrCreate(
            ['route_id' => $route->id],
            ['nodes' => [], 'edges' => []],
        );

        $this->browse(function (Browser $b) use ($route) {
            $b->resize(1440, 900)
                ->visit('/pages/'.$route->id.'/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(700);
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(700);

            // Fire a synthetic right-click on the canvas background.
            // (Dusk's rightClick uses real mouse events but the
            // canvas's @contextmenu listener also accepts a
            // synthesized event.)
            $b->script(<<<'JS'
                const root = document.querySelector('.ps-ne-canvas-wrap');
                const r = root.getBoundingClientRect();
                const evt = new MouseEvent('contextmenu', {
                    bubbles: true, cancelable: true,
                    clientX: r.left + r.width / 2,
                    clientY: r.top  + r.height / 2,
                    button: 2,
                });
                root.dispatchEvent(evt);
            JS);
            $b->pause(400);

            // Wheel should be visible.
            $hasWheel = $b->script(<<<'JS'
                const wheel = document.querySelector('.ps-ne-wheel');
                if (! wheel) return null;
                const svg = wheel.querySelector('.ps-ne-wheel-svg');
                const slices = wheel.querySelectorAll('.ps-ne-wheel-slice');
                return {
                    exists:    !! wheel,
                    svgVisible: svg ? getComputedStyle(svg).display !== 'none' : false,
                    sliceCount: slices.length,
                };
            JS)[0];

            $this->assertNotNull($hasWheel, '.ps-ne-wheel must be in the DOM after right-click');
            $this->assertTrue((bool) $hasWheel['svgVisible'],
                'wheel SVG should be visible at stage 1 · got '.json_encode($hasWheel));
            $this->assertGreaterThanOrEqual(5, (int) $hasWheel['sliceCount'],
                'wheel should have one slice per node group · got '.json_encode($hasWheel));

            // Capture the wheel + an expanded panel for visual review.
            $b->screenshot('weapon-wheel-stage-1');
            $b->script(<<<'JS'
                const root = document.querySelector('[x-data*="pageStudioNodeCanvas"]');
                Alpine.$data(root).ctxMenu.expandedGroup = 'image';
            JS);
            $b->pause(400);
            $b->screenshot('weapon-wheel-stage-2-image');
            $b->script(<<<'JS'
                const root = document.querySelector('[x-data*="pageStudioNodeCanvas"]');
                Alpine.$data(root).ctxMenu.expandedGroup = null;
            JS);
            $b->pause(200);

            // Click the source slice via Alpine directly · simpler
            // than locating the SVG path geometry.
            $b->script(<<<'JS'
                const root = document.querySelector('[x-data*="pageStudioNodeCanvas"]');
                Alpine.$data(root).ctxMenu.expandedGroup = 'source';
            JS);
            $b->pause(300);

            // Expanded panel for `source` must render with at least
            // one item.
            $panel = $b->script(<<<'JS'
                const visible = Array.from(document.querySelectorAll('.ps-ne-wheel-panel'))
                    .find(el => getComputedStyle(el).display !== 'none');
                if (! visible) return null;
                const items = visible.querySelectorAll('.ps-ne-wheel-item');
                return {
                    title: visible.querySelector('.ps-ne-wheel-panel-title')?.textContent?.trim(),
                    itemCount: items.length,
                };
            JS)[0];

            $this->assertNotNull($panel, 'an expanded panel must be visible after picking a slice');
            $this->assertSame('Source', $panel['title'], 'panel title should match the picked group');
            $this->assertGreaterThan(2, (int) $panel['itemCount'],
                'Source group should expose multiple node types · got '.json_encode($panel));

            // Click an item to drop a node · check the engine got it.
            $b->script(<<<'JS'
                const visible = Array.from(document.querySelectorAll('.ps-ne-wheel-panel'))
                    .find(el => getComputedStyle(el).display !== 'none');
                visible.querySelector('.ps-ne-wheel-item').click();
            JS);
            $b->pause(700);

            $nodeCount = $b->script(<<<'JS'
                return (Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('nodes') || []).length;
            JS)[0];

            $this->assertSame(1, (int) $nodeCount,
                'clicking an item in the expanded wheel panel should add a node · got count='.$nodeCount);

            // Wheel must close after the drop.
            $closed = $b->script(<<<'JS'
                const wheel = document.querySelector('.ps-ne-wheel');
                return wheel ? getComputedStyle(wheel).display === 'none' : true;
            JS)[0];
            $this->assertTrue((bool) $closed,
                'wheel should close after dropping a node');
        });

        \LoggedCloud\PageStudio\Models\NodeGraph::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\Page::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteDefinition::where('id', $route->id)->delete();
    }
}
