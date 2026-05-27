<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Hits the LIVE studio container's /playground at phone width to
 * reproduce what the user is seeing on the deployed site (the lab's
 * own /pages/{id}/edit was passing the existing Dusk tests but
 * studio's playground was reported as still broken).
 *
 * Captures a screenshot every run so we can EYEBALL the result · the
 * regular position/size assertions weren't catching whatever's
 * actually going wrong.
 */
class StudioPlaygroundMobileTest extends DuskTestCase
{
    public function test_studio_playground_at_a_range_of_phone_widths(): void
    {
        // Provision a lab Page + NodeGraph that mirrors the studio
        // showcase route's shape · we hit it via /pages-by-id so the
        // editor mounts by pageId only (same way studio's playground
        // does) and the test runs against MY current source rather
        // than the v2.8.2 installed in studio's vendor.
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::firstOrCreate(
            ['name' => 'dusk.studio-mobile'],
            ['method' => 'GET', 'path_template' => '/dusk-studio-mobile'],
        );
        $page = \LoggedCloud\PageStudio\Models\Page::firstOrCreate(
            ['route_id' => $route->id],
            ['blocks' => [], 'status' => 'draft'],
        );
        // Mirror /showcase/vintage's spread · multiple branches
        // scattered across the canvas so the auto-fit has work to do.
        \LoggedCloud\PageStudio\Models\NodeGraph::updateOrCreate(
            ['route_id' => $route->id],
            [
                'nodes' => [
                    ['id' => 'src',  'type' => 'image.source',     'settings' => ['url' => 'https://placehold.co/200x140'], 'position' => ['x' => 0, 'y' => 360]],
                    ['id' => 'sep',  'type' => 'image.sepia',      'settings' => ['value' => '1.0'], 'position' => ['x' => 320, 'y' => 220]],
                    ['id' => 'br',   'type' => 'image.brightness', 'settings' => ['value' => '1.1'], 'position' => ['x' => 320, 'y' => 380]],
                    ['id' => 'bl',   'type' => 'image.blur',       'settings' => ['value' => '0.5'], 'position' => ['x' => 720, 'y' => 380]],
                    ['id' => 'out1', 'type' => 'output',           'settings' => ['name' => 'sepia'],   'position' => ['x' => 1640, 'y' => 220]],
                    ['id' => 'out2', 'type' => 'output',           'settings' => ['name' => 'vintage'], 'position' => ['x' => 1640, 'y' => 380]],
                ],
                'edges' => [
                    ['id' => 'e1', 'from_node' => 'src',  'from_socket' => 'image', 'to_node' => 'sep',  'to_socket' => 'image'],
                    ['id' => 'e2', 'from_node' => 'src',  'from_socket' => 'image', 'to_node' => 'br',   'to_socket' => 'image'],
                    ['id' => 'e3', 'from_node' => 'br',   'from_socket' => 'image', 'to_node' => 'bl',   'to_socket' => 'image'],
                    ['id' => 'e4', 'from_node' => 'sep',  'from_socket' => 'image', 'to_node' => 'out1', 'to_socket' => 'value'],
                    ['id' => 'e5', 'from_node' => 'bl',   'from_socket' => 'image', 'to_node' => 'out2', 'to_socket' => 'value'],
                ],
            ],
        );

        foreach ([
            ['name' => 'iphone-se',     'w' => 375,  'h' => 667],
            ['name' => 'iphone-13',     'w' => 390,  'h' => 844],
            ['name' => 'iphone-15-pro-max', 'w' => 430, 'h' => 932],
            ['name' => 'ipad-mini',     'w' => 768,  'h' => 1024],
            ['name' => 'ipad-pro',      'w' => 1024, 'h' => 1366],
        ] as $vp) {
            $this->browse(function (Browser $b) use ($vp) {
                $b->resize($vp['w'], $vp['h'])
                    ->visit('http://studio-logged-nginx/playground?page=82')
                    ->waitFor('[data-component="page-studio.page-builder"]', 8);
                $b->pause(900);
                $b->script(<<<'JS'
                    const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                    if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
                JS);
                // Give the drawer a beat to render + the auto-fit
                // animation to land before snapping.
                $b->pause(1500);
                $b->screenshot('mobile-fit-' . $vp['name']);
            });
        }

        \LoggedCloud\PageStudio\Models\NodeGraph::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\Page::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteDefinition::where('id', $route->id)->delete();
    }

    public function test_studio_playground_at_phone_width_renders_drawer_full_screen(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(390, 844)
                ->visit('http://studio-logged-nginx/playground?page=82')
                ->waitFor('[data-component="page-studio.page-builder"]', 8);
            $b->pause(900);

            // Force-open the drawer · the test is about its open
            // state, not the tuck handle's own toggle behaviour.
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(900);

            // Eyeball capture · what does the user actually see?
            $b->screenshot('studio-mobile-drawer-open');

            // Diagnose what's on the page · all positions + computed
            // styles in one go so we can read the failure mode.
            $diag = $b->script(<<<'JS'
                const out = { vp: { w: window.innerWidth, h: window.innerHeight } };

                const grab = (sel) => {
                    const el = document.querySelector(sel);
                    if (! el) return null;
                    const r = el.getBoundingClientRect();
                    const cs = getComputedStyle(el);
                    return {
                        left: Math.round(r.left), top: Math.round(r.top),
                        width: Math.round(r.width), height: Math.round(r.height),
                        display: cs.display, position: cs.position, zIndex: cs.zIndex,
                    };
                };

                out.drawer    = grab('.ps-ne-drawer');
                out.varStrip  = grab('.ps-pb-var-strip');
                out.tuck      = grab('.ps-ne-tuck-handle');
                out.canvas    = grab('.ps-ne-canvas, .ps-ne-stage-wrap, .ps-ne-grid');
                out.drawerOpen = (Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))).get('drawerOpen');
                return out;
            JS)[0];

            // Write the diag so the user can read it alongside the
            // screenshot · helpful for triangulating the exact CSS
            // rule that's misbehaving.
            file_put_contents(
                __DIR__.'/screenshots/studio-mobile-drawer-open.json',
                json_encode($diag, JSON_PRETTY_PRINT),
            );

            // Assertions · drawer should fill the viewport, var
            // strip should be hidden.
            $this->assertNotNull($diag['drawer'] ?? null,
                'drawer must be in the DOM · got '.json_encode($diag));
            $this->assertLessThan(4, abs((int) $diag['drawer']['top']),
                'drawer top should be ~0 · got '.json_encode($diag));
            $this->assertGreaterThan(
                (int) ($diag['vp']['h'] * 0.9),
                (int) $diag['drawer']['height'],
                'drawer should cover ~full viewport height · got '.json_encode($diag),
            );
            $this->assertGreaterThan(
                (int) ($diag['vp']['w'] * 0.9),
                (int) $diag['drawer']['width'],
                'drawer should cover ~full viewport width · got '.json_encode($diag),
            );
            // Strip should be visible at the floor of the viewport ·
            // bottom edge near the viewport height.
            $stripBottom = (int) ($diag['varStrip']['top'] ?? 0) + (int) ($diag['varStrip']['height'] ?? 0);
            $this->assertLessThan(8, abs($stripBottom - (int) $diag['vp']['h']),
                'var strip should sit at the bottom of the viewport · got '.json_encode($diag));
        });
    }
}
