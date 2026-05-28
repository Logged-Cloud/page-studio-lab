<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * On phones the only pan mechanism was middle-mouse-drag or
 * Alt+left-drag · neither exists on a touchscreen. A plain finger
 * drag on the canvas background started a marquee selection instead
 * of panning. Touch users had no way to move around the graph.
 *
 * Pins down the fix: a single-finger touch drag on the canvas
 * background should change pan.x / pan.y, not start a marquee.
 */
class MobileCanvasPanTest extends DuskTestCase
{
    public function test_canvas_wrap_disables_browser_touch_gestures(): void
    {
        // Regression · on a real touchscreen, Chrome's gesture
        // detection took over after ~5px of finger movement and
        // cancelled our pointer-event-driven pan. The synthetic
        // PointerEvents used in the prior test bypass that, which
        // is why it passed even though the real device didn't pan.
        // Assert the CSS that disables the browser interpretation
        // is on the canvas-wrap.
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::firstOrCreate(
            ['name' => 'dusk.canvas-touch-action'],
            ['method' => 'GET', 'path_template' => '/dusk-canvas-touch-action'],
        );
        \LoggedCloud\PageStudio\Models\Page::firstOrCreate(
            ['route_id' => $route->id],
            ['blocks' => [], 'status' => 'draft'],
        );

        $this->browse(function (Browser $b) use ($route) {
            $b->resize(390, 844)
                ->visit('/pages/'.$route->id.'/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(700);
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(700);

            $touchAction = $b->script(<<<'JS'
                const root = document.querySelector('.ps-ne-canvas-wrap');
                if (! root) return null;
                return getComputedStyle(root).touchAction;
            JS)[0];

            $this->assertNotNull($touchAction, '.ps-ne-canvas-wrap must be in the DOM');
            $this->assertSame('none', $touchAction,
                'canvas-wrap must set touch-action: none so the browser does not hijack touch drags as scroll · got '.var_export($touchAction, true));
        });

        \LoggedCloud\PageStudio\Models\Page::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteDefinition::where('id', $route->id)->delete();
    }

    public function test_canvas_pointer_capture_keeps_the_pan_alive_past_short_drags(): void
    {
        // Reproduces "drag only moves a few pixels then stops" on
        // mobile · the panDrag handler should call setPointerCapture
        // on the canvas-wrap so the browser keeps routing pointermove
        // to the same element even after the gesture detector wants
        // to reinterpret the touch.
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::firstOrCreate(
            ['name' => 'dusk.long-touch-pan'],
            ['method' => 'GET', 'path_template' => '/dusk-long-touch-pan'],
        );
        \LoggedCloud\PageStudio\Models\Page::firstOrCreate(
            ['route_id' => $route->id],
            ['blocks' => [], 'status' => 'draft'],
        );

        $this->browse(function (Browser $b) use ($route) {
            $b->resize(390, 844)
                ->visit('/pages/'.$route->id.'/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(700);
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(700);

            // Spy on setPointerCapture to confirm the handler claims
            // the gesture · this is the real fix beyond touch-action.
            $captured = $b->script(<<<'JS'
                window.__psCaptured = false;
                const orig = HTMLElement.prototype.setPointerCapture;
                HTMLElement.prototype.setPointerCapture = function (id) {
                    window.__psCaptured = true;
                    return orig.call(this, id);
                };

                const root = document.querySelector('.ps-ne-canvas-wrap');
                const r = root.getBoundingClientRect();
                const x0 = Math.round(r.left + r.width / 2);
                const y0 = Math.round(r.top  + r.height / 2);

                // Synthesize a pointerdown only · the capture should
                // happen on down, BEFORE any move arrives.
                const evt = new PointerEvent('pointerdown', {
                    pointerType: 'touch', pointerId: 1, isPrimary: true,
                    button: 0, clientX: x0, clientY: y0,
                    bubbles: true, cancelable: true,
                });
                root.dispatchEvent(evt);

                const result = window.__psCaptured;
                HTMLElement.prototype.setPointerCapture = orig;
                return result;
            JS)[0];

            $this->assertTrue((bool) $captured,
                'canvas pan handler should call setPointerCapture on touch pointerdown · without it the browser can steal the gesture mid-drag');
        });

        \LoggedCloud\PageStudio\Models\Page::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteDefinition::where('id', $route->id)->delete();
    }

    public function test_shift_plus_left_drag_pans_the_canvas_on_desktop(): void
    {
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::firstOrCreate(
            ['name' => 'dusk.shift-pan'],
            ['method' => 'GET', 'path_template' => '/dusk-shift-pan'],
        );
        \LoggedCloud\PageStudio\Models\Page::firstOrCreate(
            ['route_id' => $route->id],
            ['blocks' => [], 'status' => 'draft'],
        );
        \LoggedCloud\PageStudio\Models\NodeGraph::updateOrCreate(
            ['route_id' => $route->id],
            [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'source.constant', 'settings' => ['value' => 'pan'], 'position' => ['x' => 400, 'y' => 200]],
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
            JS);
            $b->pause(700);

            $pre = $b->script(<<<'JS'
                const root = document.querySelector('.ps-ne-canvas-wrap');
                const scope = Alpine.$data(root);
                return { x: scope.pan.x, y: scope.pan.y };
            JS)[0];

            // Mouse drag with shiftKey · should pan the stage just
            // like the existing Alt+left and middle-mouse triggers.
            $b->script(<<<'JS'
                const root = document.querySelector('.ps-ne-canvas-wrap');
                const r = root.getBoundingClientRect();
                const x0 = Math.round(r.left + r.width / 2);
                const y0 = Math.round(r.top  + r.height / 2);
                const fire = (type, dx, dy) => {
                    const evt = new PointerEvent(type, {
                        pointerType: 'mouse',
                        button: 0,
                        clientX: x0 + dx,
                        clientY: y0 + dy,
                        shiftKey: true,
                        bubbles: true,
                        cancelable: true,
                    });
                    root.dispatchEvent(evt);
                    window.dispatchEvent(evt);
                };
                fire('pointerdown',  0,  0);
                fire('pointermove', 50, 25);
                fire('pointermove', 100, 50);
                fire('pointerup',  100, 50);
            JS);
            $b->pause(400);

            $post = $b->script(<<<'JS'
                const root = document.querySelector('.ps-ne-canvas-wrap');
                const scope = Alpine.$data(root);
                return { x: scope.pan.x, y: scope.pan.y, marqueeActive: scope.marquee?.active === true };
            JS)[0];

            $this->assertGreaterThan(50, (int) $post['x'] - (int) $pre['x'],
                'Shift+left-drag should pan x by the drag delta · got pre='.json_encode($pre).' post='.json_encode($post));
            $this->assertGreaterThan(20, (int) $post['y'] - (int) $pre['y'],
                'Shift+left-drag should pan y by the drag delta · got pre='.json_encode($pre).' post='.json_encode($post));
            $this->assertFalse((bool) ($post['marqueeActive'] ?? false),
                'Shift+left-drag must pan, not leave a marquee active');
        });

        \LoggedCloud\PageStudio\Models\NodeGraph::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\Page::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteDefinition::where('id', $route->id)->delete();
    }

    public function test_single_finger_touch_drag_pans_the_canvas(): void
    {
        // Seed a route + open the editor at phone width.
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::firstOrCreate(
            ['name' => 'dusk.mobile-pan'],
            ['method' => 'GET', 'path_template' => '/dusk-mobile-pan'],
        );
        \LoggedCloud\PageStudio\Models\Page::firstOrCreate(
            ['route_id' => $route->id],
            ['blocks' => [], 'status' => 'draft'],
        );
        \LoggedCloud\PageStudio\Models\NodeGraph::updateOrCreate(
            ['route_id' => $route->id],
            [
                'nodes' => [
                    // One node parked at canvas-x=400 so the pan
                    // delta is observable both in graph state and
                    // visually (node shifts as we drag).
                    ['id' => 'n1', 'type' => 'source.constant', 'settings' => ['value' => 'pan'], 'position' => ['x' => 400, 'y' => 200]],
                ],
                'edges' => [],
            ],
        );

        $this->browse(function (Browser $b) use ($route) {
            $b->resize(390, 844)
                ->visit('/pages/'.$route->id.'/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(700);

            // Open the drawer so the canvas is on screen.
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(700);

            // Capture pre-drag pan via the canvas's Alpine scope.
            $pre = $b->script(<<<'JS'
                const root = document.querySelector('.ps-ne-canvas-wrap');
                const scope = Alpine.$data(root);
                return { x: scope.pan.x, y: scope.pan.y };
            JS)[0];

            // Simulate a single-finger touch drag · pointerdown +
            // moves + pointerup with pointerType:'touch'. Drag from
            // the centre of the canvas-wrap (background, no node)
            // 120px to the right and 60px down.
            $b->script(<<<'JS'
                const root = document.querySelector('.ps-ne-canvas-wrap');
                const r = root.getBoundingClientRect();
                const x0 = Math.round(r.left + r.width / 2);
                const y0 = Math.round(r.top  + r.height / 2);
                const fire = (type, dx, dy) => {
                    const evt = new PointerEvent(type, {
                        pointerType: 'touch',
                        button: 0,
                        clientX: x0 + dx,
                        clientY: y0 + dy,
                        bubbles: true,
                        cancelable: true,
                    });
                    root.dispatchEvent(evt);
                };
                fire('pointerdown', 0, 0);
                fire('pointermove',  40, 20);
                fire('pointermove',  80, 40);
                fire('pointermove', 120, 60);
                fire('pointerup',   120, 60);
            JS);
            $b->pause(500);

            $post = $b->script(<<<'JS'
                const root = document.querySelector('.ps-ne-canvas-wrap');
                const scope = Alpine.$data(root);
                return { x: scope.pan.x, y: scope.pan.y, marqueeActive: scope.marquee?.active === true };
            JS)[0];

            // The drag delta was +120, +60 · pan should have moved
            // by roughly that amount (sign + small slack).
            $this->assertGreaterThan(60, (int) $post['x'] - (int) $pre['x'],
                'pan.x should have advanced by the touch drag delta · got pre='.json_encode($pre).' post='.json_encode($post));
            $this->assertGreaterThan(30, (int) $post['y'] - (int) $pre['y'],
                'pan.y should have advanced by the touch drag delta · got pre='.json_encode($pre).' post='.json_encode($post));

            // The marquee selection box must NOT have been left active
            // by the touch · the drag is meant to pan, not select.
            $this->assertFalse((bool) ($post['marqueeActive'] ?? false),
                'touch pan must not leave the marquee active');
        });

        \LoggedCloud\PageStudio\Models\NodeGraph::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\Page::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteDefinition::where('id', $route->id)->delete();
    }
}
