<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Phone-width layout · rails should default to closed, the canvas should
 * own the screen, and tapping the toolbar toggles should slide the rails
 * in as overlays. Also captures a `mobile.png` for the README.
 */
class MobileLayoutTest extends DuskTestCase
{
    protected function readProp(Browser $b, string $prop): mixed
    {
        return $b->script(
            "return Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).get('$prop');"
        )[0];
    }

    public function test_phone_width_starts_with_both_rails_collapsed(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(375, 750)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5)
                ->pause(500);

            $hasLeftCollapsed = $b->script("return document.querySelector('.ps-pb-grid').classList.contains('is-left-collapsed');")[0];
            $hasRightCollapsed = $b->script("return document.querySelector('.ps-pb-grid').classList.contains('is-right-collapsed');")[0];

            $this->assertTrue($hasLeftCollapsed, 'Left rail should default to collapsed on phone width');
            $this->assertTrue($hasRightCollapsed, 'Right rail should default to collapsed on phone width');
        });
    }

    public function test_tapping_the_left_rail_toggle_slides_it_in(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(375, 750)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5)
                ->pause(500);

            // First toolbar button is the left rail toggle.
            $b->script("document.querySelector('.ps-pb-rail-toggle').click();");
            $b->pause(400);

            $hasLeftCollapsed = $b->script("return document.querySelector('.ps-pb-grid').classList.contains('is-left-collapsed');")[0];
            $this->assertFalse($hasLeftCollapsed, 'Left rail toggle should clear the is-left-collapsed class');

            // The rail should be visible (translateX(0)) · sniff its transform.
            $transform = $b->script("return window.getComputedStyle(document.querySelector('.ps-pb-rail--left')).transform;")[0];
            $this->assertNotSame('matrix(1, 0, 0, 1, -800, 0)', $transform,
                'Rail should not be off-screen once the toggle is tapped · transform was: '.$transform);
        });
    }

    public function test_captures_mobile_layout_screenshot_for_the_readme(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(390, 844)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5)
                ->pause(700);

            // Seed a richer block tree so the canvas looks like a real page.
            $b->script(<<<'JS'
                const c = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                c.set('blocks', [
                    { id: 'm1', type: 'heading',   settings: { text: 'Build pages on the go', level: 'h1', align: 'left' } },
                    { id: 'm2', type: 'paragraph', settings: { text: 'The studio is mobile-friendly · rails slide in, the canvas fills the screen.' } },
                    { id: 'm3', type: 'divider',   settings: {} },
                    { id: 'm4', type: 'button',    settings: { label: 'Get started', href: '/sign-up', variant: 'primary' } },
                ]);
            JS);
            $b->pause(800);
            $b->screenshot('mobile-layout');
        });
    }
}
