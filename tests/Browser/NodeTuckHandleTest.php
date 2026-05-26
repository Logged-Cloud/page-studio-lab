<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Tuck handle for the node drawer · mirrors the logged-cloud/navigation
 * package's docked-chrome pattern. A small pill fixed at the bottom of
 * the viewport is the always-visible affordance to open / close the
 * node editor. The drawer is fixed-positioned when open so a long
 * canvas doesn't push it below the fold.
 */
class NodeTuckHandleTest extends DuskTestCase
{
    protected function fresh(Browser $b, int $width = 1440, int $height = 900): void
    {
        $b->resize($width, $height)
            ->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->pause(400);
        // Make sure drawer starts closed.
        $b->script(<<<'JS'
            const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
            if (wire.get('drawerOpen')) wire.call('toggleDrawer');
        JS);
        $b->pause(400);
    }

    public function test_tuck_handle_is_always_visible_at_the_bottom_of_the_viewport(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $info = $b->script(<<<'JS'
                const h = document.querySelector('.ps-ne-tuck-handle');
                if (! h) return null;
                const r = h.getBoundingClientRect();
                const cs = window.getComputedStyle(h);
                return {
                    position: cs.position,
                    bottom: r.bottom,
                    viewportH: window.innerHeight,
                    visible: r.width > 0 && r.height > 0,
                };
            JS)[0];

            $this->assertNotNull($info, '.ps-ne-tuck-handle should exist · is the package on v2.4.6+');
            $this->assertSame('fixed', $info['position'], 'Tuck handle should be position:fixed');
            $this->assertTrue($info['visible'], 'Tuck handle should be visible');
            // Within 80px of the bottom of the viewport · gives margin for the handle's own height + offset.
            $this->assertLessThan(80, $info['viewportH'] - $info['bottom'],
                'Tuck handle should sit near the bottom edge · was '.($info['viewportH'] - $info['bottom']).'px from bottom');
        });
    }

    public function test_clicking_the_tuck_handle_opens_the_drawer(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $b->script("document.querySelector('.ps-ne-tuck-handle').click();");
            $b->pause(700);

            $open = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('drawerOpen');
            JS)[0];
            $this->assertTrue((bool) $open, 'Drawer should be open after tapping the tuck handle');

            // And the drawer should be fixed-positioned, not below-the-fold.
            $pos = $b->script(<<<'JS'
                const d = document.querySelector('.ps-ne-drawer');
                return d ? window.getComputedStyle(d).position : null;
            JS)[0];
            $this->assertSame('fixed', $pos,
                'Open drawer should be position:fixed so canvas scroll never hides it · was '.var_export($pos, true));
        });
    }

    public function test_clicking_the_tuck_handle_a_second_time_closes_the_drawer(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $b->script("document.querySelector('.ps-ne-tuck-handle').click();");
            $b->pause(500);
            $b->script("document.querySelector('.ps-ne-tuck-handle').click();");
            $b->pause(500);

            $open = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('drawerOpen');
            JS)[0];
            $this->assertFalse((bool) $open, 'Drawer should be closed after a second tap');
        });
    }

    public function test_tuck_handle_works_on_phone_too(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b, 390, 844);

            $exists = $b->script("return !! document.querySelector('.ps-ne-tuck-handle');")[0];
            $this->assertTrue((bool) $exists, 'Tuck handle should exist on phone too');

            $b->script("document.querySelector('.ps-ne-tuck-handle').click();");
            $b->pause(600);

            $open = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('drawerOpen');
            JS)[0];
            $this->assertTrue((bool) $open, 'Tap on phone should open the drawer');
        });
    }

    public function test_visual_tuck_handle_closed_and_open(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->screenshot('tuck-handle-closed');

            $b->script("document.querySelector('.ps-ne-tuck-handle').click();");
            $b->pause(600);
            $b->screenshot('tuck-handle-open-drawer');
        });
    }

    public function test_drawer_remains_visible_when_canvas_content_is_tall(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            // Open the drawer FIRST · before seeding tall content. Doing
            // both in the same Livewire round-trip races; this test only
            // cares that an open drawer stays in view after a scroll.
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('toggleDrawer');
            JS);
            $b->pause(700);

            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                const blocks = [];
                for (let i = 0; i < 40; i++) {
                    blocks.push({ type: 'paragraph', settings: { text: 'Filler paragraph ' + i } });
                }
                wire.set('blocks', blocks);
            JS);
            $b->pause(800);

            $open = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('drawerOpen');
            JS)[0];
            $this->assertTrue((bool) $open, 'Drawer should be open before scrolling · toggle failed');

            // Scroll the canvas all the way down.
            $b->script(<<<'JS'
                const canvas = document.querySelector('.ps-pb-canvas-wrap');
                if (canvas) canvas.scrollTop = canvas.scrollHeight;
                window.scrollTo(0, document.body.scrollHeight);
            JS);
            $b->pause(400);

            $info = $b->script(<<<'JS'
                const d = document.querySelector('.ps-ne-drawer');
                if (! d) return { exists: false };
                const r = d.getBoundingClientRect();
                const cs = window.getComputedStyle(d);
                return {
                    exists: true,
                    position: cs.position,
                    top: r.top,
                    bottom: r.bottom,
                    viewportH: window.innerHeight,
                    inView: r.bottom > 0 && r.top < window.innerHeight,
                };
            JS)[0];

            $this->assertTrue((bool) ($info['exists'] ?? false),
                'Drawer should be in the DOM · got '.json_encode($info));
            $this->assertSame('fixed', $info['position'] ?? null,
                'Drawer should be position:fixed · got '.json_encode($info));
            $this->assertTrue((bool) ($info['inView'] ?? false),
                'Drawer should stay in view after scrolling · got '.json_encode($info));
        });
    }
}
