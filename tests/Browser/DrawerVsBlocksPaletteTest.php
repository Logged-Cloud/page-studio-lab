<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Regression · the Variables Modifier drawer is position:fixed across
 * the full viewport bottom. The blocks palette (left rail) sits in the
 * grid above it; without a bottom-reservation the lower portion of the
 * palette gets covered by the drawer and its scroll-tail becomes
 * unreachable.
 *
 * This test seeds enough rail content to push it past the natural
 * viewport height (the full block palette is ~16 entries), opens the
 * drawer, and asserts the palette's bottom edge sits above the drawer's
 * top edge so nothing is occluded.
 */
class DrawerVsBlocksPaletteTest extends DuskTestCase
{
    protected function fresh(Browser $b): void
    {
        $b->resize(1440, 900)
            ->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->pause(400);
        $b->script(<<<'JS'
            const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
            if (wire.get('drawerOpen')) wire.call('toggleDrawer');
        JS);
        $b->pause(400);
    }

    public function test_visual_drawer_open_with_palette_visible(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('toggleDrawer');
            JS);
            $b->pause(800);
            $b->screenshot('drawer-open-palette-visible');
        });
    }

    public function test_blocks_palette_is_not_occluded_by_an_open_drawer(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('toggleDrawer');
            JS);
            $b->pause(800);

            $info = $b->script(<<<'JS'
                const rail   = document.querySelector('.ps-pb-rail--left');
                const drawer = document.querySelector('.ps-ne-drawer');
                if (! rail || ! drawer) return null;
                const r = rail.getBoundingClientRect();
                const d = drawer.getBoundingClientRect();
                return {
                    railBottom: r.bottom,
                    drawerTop: d.top,
                    overlap: r.bottom > d.top,
                    railVisibleHeight: Math.max(0, Math.min(r.bottom, d.top) - Math.max(r.top, 0)),
                };
            JS)[0];

            $this->assertNotNull($info, 'rail + drawer should both be in the DOM');
            $this->assertFalse($info['overlap'],
                'Blocks palette must not extend behind the drawer · railBottom='.$info['railBottom']
                .' drawerTop='.$info['drawerTop']);
        });
    }

    public function test_palette_bottom_items_are_still_clickable_with_drawer_open(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('toggleDrawer');
            JS);
            $b->pause(800);

            // Find the bottom-most palette item that's currently inside
            // the rail's viewport (the rail itself scrolls; items below
            // its scroll-tail aren't visible and not what we're testing).
            // Then check elementFromPoint resolves to that item, not the
            // drawer.
            $hit = $b->script(<<<'JS'
                const rail = document.querySelector('.ps-pb-rail--left');
                if (! rail) return false;
                const railR = rail.getBoundingClientRect();
                const items = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'));
                let target = null;
                for (const it of items) {
                    const r = it.getBoundingClientRect();
                    if (r.bottom > railR.top && r.bottom <= railR.bottom) target = it;
                }
                if (! target) return false;
                const r = target.getBoundingClientRect();
                const cx = r.left + r.width / 2;
                const cy = r.top + r.height / 2;
                const hit = document.elementFromPoint(cx, cy);
                if (! hit) return false;
                if (target.contains(hit) || hit === target) return true;
                return ! hit.closest('.ps-ne-drawer');
            JS)[0];

            $this->assertTrue((bool) $hit,
                'The bottom palette item should be hit-testable · the drawer is covering it');
        });
    }
}
