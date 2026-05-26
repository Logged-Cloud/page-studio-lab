<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Mobile sheet rails · regression for the cascade bug that made the
 * left + right rail backgrounds transparent on phone-width viewports,
 * so the canvas content bled through the open rail.
 *
 * The @media (max-width: 768px) block set `background: var(--surface-2)`
 * on .ps-pb-rail, but the unqualified base rule that followed it in
 * source order set `background: rgba(255,255,255,.02)` and won the
 * cascade · the sheet appeared empty over the canvas blocks.
 *
 * This test resizes the browser to phone width, opens the left rail
 * via the topbar toggle, and asserts the rail's computed background
 * is opaque enough to hide what's behind it.
 */
class MobileRailBackgroundTest extends DuskTestCase
{
    protected function freshAtPhoneWidth(Browser $b): void
    {
        $b->resize(390, 844)
            ->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->pause(400);
    }

    public function test_left_rail_has_solid_background_on_phone(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshAtPhoneWidth($b);

            // Mobile defaults · both rails closed. Open the left rail by
            // flipping the Alpine `leftCollapsed` flag (same path the
            // topbar toggle button walks).
            $b->script(<<<'JS'
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                Alpine.$data(root).leftCollapsed = false;
            JS);
            $b->pause(500);

            // Read the computed background-color of the open rail.
            $bg = $b->script(<<<'JS'
                const rail = document.querySelector('.ps-pb-rail--left');
                return rail ? window.getComputedStyle(rail).backgroundColor : null;
            JS)[0];

            $this->assertNotNull($bg, 'Left rail element should exist on phone');

            // rgba(255,255,255,.02) computes to "rgba(255, 255, 255, 0.02)".
            // Anything resembling that = the bug is present. A solid sheet
            // background should be opaque or near-opaque.
            $this->assertStringNotContainsString('0.02', $bg,
                'Left rail should NOT have the near-transparent base background on phone · got '.$bg);

            // Snap the open-rail state for visual review.
            $b->screenshot('mobile-left-rail-open');
        });
    }

    public function test_right_rail_has_solid_background_on_phone(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshAtPhoneWidth($b);

            // Select a block first so the right rail has settings to show.
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                wire.set('blocks', [{ type: 'heading', settings: { text: 'Hello', level: 'h1', align: 'left' } }]);
            JS);
            $b->pause(500);
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                wire.call('selectBlock', '0');
            JS);
            $b->pause(500);

            $b->script(<<<'JS'
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                Alpine.$data(root).rightCollapsed = false;
            JS);
            $b->pause(500);

            $bg = $b->script(<<<'JS'
                const rail = document.querySelector('.ps-pb-rail--right');
                return rail ? window.getComputedStyle(rail).backgroundColor : null;
            JS)[0];

            $this->assertNotNull($bg);
            $this->assertStringNotContainsString('0.02', $bg,
                'Right rail should NOT have the near-transparent base background on phone · got '.$bg);
        });
    }

    public function test_palette_renders_a_two_column_grid_on_phone(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshAtPhoneWidth($b);

            $b->script(<<<'JS'
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                Alpine.$data(root).leftCollapsed = false;
            JS);
            $b->pause(400);

            $cols = $b->script(<<<'JS'
                const palette = document.querySelector('.ps-pb-palette');
                if (! palette) return null;
                return window.getComputedStyle(palette).gridTemplateColumns;
            JS)[0];

            $this->assertNotNull($cols, '.ps-pb-palette should exist on phone');
            $this->assertStringContainsString(' ', $cols,
                'Palette grid should have at least two tracks on phone (2-up layout) · got '.$cols);
        });
    }
}
