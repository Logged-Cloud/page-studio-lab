<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * On phone-sized viewports the Variables Modifier drawer should
 * cover the entire viewport so the author has the whole screen to
 * work the node graph · no cramped 80vh slice along the bottom.
 *
 * Pinned by measuring the rendered drawer's box · top must be near
 * 0 and height must be very close to viewport height.
 */
class MobileFullScreenDrawerTest extends DuskTestCase
{
    public function test_drawer_is_full_screen_when_open_on_phone(): void
    {
        $this->browse(function (Browser $b) {
            // Phone viewport · matches the @media (max-width: 768px) gate.
            $b->resize(390, 844)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(700);

            // Make sure the drawer is open.
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(600);

            $shape = $b->script(<<<'JS'
                const d = document.querySelector('.ps-ne-drawer');
                if (! d) return null;
                const r = d.getBoundingClientRect();
                return {
                    top:    Math.round(r.top),
                    height: Math.round(r.height),
                    vh:     window.innerHeight,
                };
            JS)[0];

            $this->assertNotNull($shape, '.ps-ne-drawer should be in the DOM with the drawer open');
            $this->assertLessThan(4, abs((int) $shape['top']),
                'drawer top should sit at the very top of the viewport · got '.json_encode($shape));
            // Allow a few pixels of slop for browser chrome quirks.
            $this->assertLessThan(8, abs((int) $shape['vh'] - (int) $shape['height']),
                'drawer should fill the full viewport height · got '.json_encode($shape));
        });
    }

    public function test_var_strip_pinned_to_bottom_while_drawer_is_full_screen_on_phone(): void
    {
        // The strip's `bottom: calc(var(--ps-pb-drawer-h) + 8px)`
        // was leaning on a JS-set CSS variable that carried the
        // desktop default (352px) even when the mobile media rule
        // forced the drawer to 100dvh, so the strip floated up to
        // mid-screen · cue the "vars still in the middle" report.
        // Fix · pin the strip to bottom: 0 with z-index above the
        // drawer so it stays at the floor and remains usable.
        $this->browse(function (Browser $b) {
            $b->resize(390, 844)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(700);

            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(600);

            $shape = $b->script(<<<'JS'
                const el = document.querySelector('.ps-pb-var-strip');
                if (! el) return null;
                const cs = getComputedStyle(el);
                const r = el.getBoundingClientRect();
                return {
                    display: cs.display,
                    zIndex:  parseInt(cs.zIndex || '0', 10),
                    bottom:  Math.round(window.innerHeight - r.bottom),
                    top:     Math.round(r.top),
                };
            JS)[0];

            $this->assertNotNull($shape, 'var-strip must be present');
            $this->assertNotSame('none', $shape['display'],
                'var-strip must remain visible while the full-screen drawer is open · got '.json_encode($shape));
            $this->assertLessThan(4, abs((int) $shape['bottom']),
                'var-strip must sit at the bottom of the viewport (bottom ~ 0) · got '.json_encode($shape));
            $this->assertGreaterThanOrEqual(75, (int) $shape['zIndex'],
                'var-strip z-index must clear the drawer (70) so it floats on top · got '.json_encode($shape));
        });
    }

    public function test_drawer_stays_bottom_anchored_on_desktop(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(700);

            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(600);

            $shape = $b->script(<<<'JS'
                const d = document.querySelector('.ps-ne-drawer');
                if (! d) return null;
                const r = d.getBoundingClientRect();
                return {
                    top:    Math.round(r.top),
                    height: Math.round(r.height),
                    vh:     window.innerHeight,
                };
            JS)[0];

            $this->assertNotNull($shape);
            // Desktop · drawer is much smaller than the viewport (the
            // canvas sits behind it). Anything under ~70% of the
            // viewport counts as "not full screen".
            $this->assertLessThan(
                (int) ($shape['vh'] * 0.7),
                (int) $shape['height'],
                'on desktop the drawer must NOT cover the viewport · got '.json_encode($shape),
            );
        });
    }
}
