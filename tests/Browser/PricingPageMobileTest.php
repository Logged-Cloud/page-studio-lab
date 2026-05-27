<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Diagnose the user's "page view is cut off + huge empty space" report
 * on the live studio's /playground at phone width. Targets page 82 ·
 * the pricing demo page they shared a screenshot of.
 */
class PricingPageMobileTest extends DuskTestCase
{
    public function test_capture_pricing_page_diagnostic(): void
    {
        // Seed a pricing-like page in the lab (which symlinks to my
        // unreleased package source) so we can verify the fix BEFORE
        // bumping studio's installed version.
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::firstOrCreate(
            ['name' => 'dusk.pricing-mobile'],
            ['method' => 'GET', 'path_template' => '/dusk-pricing-mobile'],
        );
        $page = \LoggedCloud\PageStudio\Models\Page::updateOrCreate(
            ['route_id' => $route->id],
            [
                'blocks' => [
                    ['id' => 'h', 'type' => 'heading', 'settings' => ['text' => 'Simple, honest pricing', 'level' => 'h1', 'align' => 'center']],
                    ['id' => 'p', 'type' => 'paragraph', 'settings' => ['text' => 'Pick the tier that fits today, change it whenever you like.']],
                    ['id' => 'c', 'type' => 'columns-3', 'settings' => [], 'children' => [
                        'left'   => [['id' => 'l', 'type' => 'paragraph', 'settings' => ['text' => 'Drop a block']]],
                        'middle' => [['id' => 'm', 'type' => 'paragraph', 'settings' => ['text' => 'Drop a block']]],
                        'right'  => [['id' => 'r', 'type' => 'paragraph', 'settings' => ['text' => 'Drop a block']]],
                    ]],
                ],
                'status' => 'draft',
            ],
        );

        $this->browse(function (Browser $b) use ($page) {
            $b->resize(390, 844)
                ->visit('http://studio-logged-nginx/playground?page=82')
                ->waitFor('[data-component="page-studio.page-builder"]', 8);
            $b->pause(900);

            $b->screenshot('pricing-mobile-closed-drawer');

            // Also capture the drawer-open state · the drawer header
            // bar (Palette / Variables Modifier / undo/redo / etc.)
            // needed the same horizontal-scroll treatment as the
            // outer topbar.
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(900);
            $b->screenshot('pricing-mobile-open-drawer-bar');

            $diag = $b->script(<<<'JS'
                const out = { vp: { w: window.innerWidth, h: window.innerHeight } };
                const grab = (sel, label) => {
                    const el = document.querySelector(sel);
                    if (! el) { out[label || sel] = null; return; }
                    const r = el.getBoundingClientRect();
                    const cs = getComputedStyle(el);
                    out[label || sel] = {
                        left: Math.round(r.left), top: Math.round(r.top),
                        width: Math.round(r.width), height: Math.round(r.height),
                        bottom: Math.round(r.bottom),
                        display: cs.display, position: cs.position,
                        paddingBottom: cs.paddingBottom,
                        overflow: cs.overflow,
                    };
                };
                grab('.ps-pb-grid', 'grid');
                grab('.ps-pb-canvas-wrap', 'canvasWrap');
                grab('.ps-pb-preview-pane', 'previewPane');
                grab('.ps-pb-page-frame, .ps-pb-page', 'pageFrame');
                grab('.ps-pb-var-strip', 'varStrip');
                grab('.ps-ne-tuck-handle', 'tuckHandle');
                out.drawerHCss = getComputedStyle(document.documentElement).getPropertyValue('--ps-pb-drawer-h');
                out.drawerOpen = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('drawerOpen');
                return out;
            JS)[0];

            file_put_contents(__DIR__.'/screenshots/pricing-mobile-closed-drawer.json',
                json_encode($diag, JSON_PRETTY_PRINT));
        });
    }
}
