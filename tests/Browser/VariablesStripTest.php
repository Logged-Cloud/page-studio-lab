<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Persistent variables strip · a horizontal, horizontally-scrollable
 * marquee of variable chips that sits just above the Variables Modifier
 * drawer. Always visible (or at least addressable) regardless of canvas
 * scroll. Each chip is the {{ name }} token the author drops into a
 * text field.
 *
 * Tests:
 *   1. The strip element exists and is position:fixed.
 *   2. It sits just above the drawer · top of strip = bottom of viewport
 *      minus drawer height (or close).
 *   3. Each declared route variable renders as a chip carrying the
 *      `{{ name }}` token.
 *   4. The strip is horizontally scrollable (overflow-x: auto).
 *   5. Chips are draggable · dispatching a dragstart on one writes the
 *      `{{ name }}` token to the dataTransfer text/plain payload.
 */
class VariablesStripTest extends DuskTestCase
{
    protected function fresh(Browser $b): void
    {
        $b->resize(1440, 900)
            ->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->pause(400);
        // Ensure drawer starts closed so toggleDrawer in tests reliably opens.
        $b->script(<<<'JS'
            const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
            if (wire.get('drawerOpen')) wire.call('toggleDrawer');
        JS);
        $b->pause(500);
    }

    public function test_strip_exists_and_is_fixed(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $info = $b->script(<<<'JS'
                const s = document.querySelector('.ps-pb-var-strip');
                if (! s) return null;
                const cs = window.getComputedStyle(s);
                const r = s.getBoundingClientRect();
                return {
                    position: cs.position,
                    visible: r.width > 0 && r.height > 0,
                    overflowX: cs.overflowX,
                };
            JS)[0];

            $this->assertNotNull($info, '.ps-pb-var-strip must exist');
            $this->assertSame('fixed', $info['position'],
                'Variable strip must be position:fixed so it does not move with page scroll');
            $this->assertTrue($info['visible'], 'Strip should be visible');
            $this->assertTrue(in_array($info['overflowX'], ['auto', 'scroll'], true),
                'Strip must scroll horizontally · overflow-x was '.$info['overflowX']);
        });
    }

    public function test_strip_sits_just_above_the_drawer(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            // Open the drawer · the strip should ride up to sit above it.
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('toggleDrawer');
            JS);
            $b->waitFor('.ps-ne-drawer', 5);
            $b->pause(600);

            $info = $b->script(<<<'JS'
                const s = document.querySelector('.ps-pb-var-strip');
                const d = document.querySelector('.ps-ne-drawer');
                return {
                    stripFound: !! s,
                    drawerFound: !! d,
                    stripBottom: s ? s.getBoundingClientRect().bottom : null,
                    drawerTop: d ? d.getBoundingClientRect().top : null,
                    gap: (s && d) ? d.getBoundingClientRect().top - s.getBoundingClientRect().bottom : null,
                };
            JS)[0];

            $this->assertTrue($info['stripFound'], 'strip should be in DOM · got '.json_encode($info));
            $this->assertTrue($info['drawerFound'], 'drawer should be in DOM · got '.json_encode($info));
            $this->assertLessThan(20, abs($info['gap']),
                'Strip bottom should sit within 20px of the drawer top · got '.json_encode($info));
        });
    }

    public function test_strip_renders_a_chip_per_route_variable(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            // The dusk.test route ships with at least one segment-bound
            // variable (userId · the /dusk/{userId} pattern).
            $needles = $b->script(<<<'JS'
                const chips = Array.from(document.querySelectorAll('.ps-pb-var-strip [data-var-name]'));
                return chips.map(c => c.getAttribute('data-var-name'));
            JS)[0];

            $this->assertNotEmpty($needles, 'At least one variable chip should render · got '.json_encode($needles));
            $this->assertContains('userId', $needles ?: [],
                'The userId route variable should render as a chip · got '.json_encode($needles));
        });
    }

    public function test_visual_strip_closed_and_open_drawer(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->screenshot('var-strip-drawer-closed');

            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('toggleDrawer');
            JS);
            $b->waitFor('.ps-ne-drawer', 5);
            $b->pause(500);
            $b->screenshot('var-strip-drawer-open');
        });
    }

    public function test_chip_dragstart_writes_the_var_token_to_dataTransfer(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $payload = $b->script(<<<'JS'
                const chip = document.querySelector('.ps-pb-var-strip [data-var-name="userId"]');
                if (! chip) return null;
                const dt = new DataTransfer();
                chip.dispatchEvent(new DragEvent('dragstart', { dataTransfer: dt, bubbles: true, cancelable: true }));
                return dt.getData('text/plain');
            JS)[0];

            $this->assertSame('{{ userId }}', $payload,
                'Chip dragstart should write `{{ userId }}` to dataTransfer · got '.var_export($payload, true));
        });
    }
}
