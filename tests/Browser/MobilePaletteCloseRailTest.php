<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Mobile palette UX · selecting / dragging a palette item should close
 * the left rail so the canvas is visible to drop onto. On phones the
 * rail is a slide-in sheet that occupies most of the viewport · without
 * the auto-close, a click-to-add succeeds but the author doesn't see
 * the new block until they tap the toggle, and a drag has no visible
 * drop target.
 *
 * Wider-than-phone viewports keep the rail open as before · the auto-
 * close is purely a phone-shape behaviour.
 */
class MobilePaletteCloseRailTest extends DuskTestCase
{
    protected function freshAtPhoneWidth(Browser $b): void
    {
        $b->resize(390, 844)
            ->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->pause(400);
        // Reset block tree so we can count what the tap added.
        $b->script(<<<'JS'
            Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).set('blocks', []);
        JS);
        $b->pause(300);
    }

    protected function openLeftRail(Browser $b): void
    {
        $b->script(<<<'JS'
            const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
            Alpine.$data(root).leftCollapsed = false;
        JS);
        $b->pause(300);
    }

    protected function readLeftCollapsed(Browser $b): bool
    {
        $val = $b->script(<<<'JS'
            const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
            return Alpine.$data(root).leftCollapsed;
        JS)[0];
        return (bool) $val;
    }

    public function test_tapping_a_palette_item_closes_the_left_rail_on_phone(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshAtPhoneWidth($b);
            $this->openLeftRail($b);

            $this->assertFalse($this->readLeftCollapsed($b),
                'Sanity: rail should be open before the tap');

            // Tap the Heading palette item (same path the production click
            // handler walks · @click="$wire.addBlock(type)" plus the new
            // auto-close hook).
            $b->script(<<<'JS'
                const btn = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Heading'));
                if (btn) btn.click();
            JS);
            $b->pause(700);

            $this->assertTrue($this->readLeftCollapsed($b),
                'Rail should auto-close on phone after tapping a palette item · was: '.($this->readLeftCollapsed($b) ? 'closed' : 'open'));

            // And the block should have been added (the auto-close mustn't
            // swallow the addBlock action).
            $blocks = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('blocks');
            JS)[0];
            $this->assertCount(1, $blocks, 'Tap should have added one block · got '.json_encode($blocks));
            $this->assertSame('heading', $blocks[0]['type'] ?? null);
        });
    }

    public function test_dragstart_from_palette_closes_the_left_rail_on_phone(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshAtPhoneWidth($b);
            $this->openLeftRail($b);

            // Fire a real dragstart on a palette item · same event the
            // browser would dispatch when the user pinches and drags. The
            // production handler is @dragstart.stop="onPaletteDragStart"
            // plus the new rail-close.
            $b->script(<<<'JS'
                const btn = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Paragraph'));
                if (btn) {
                    btn.dispatchEvent(new DragEvent('dragstart', {
                        dataTransfer: new DataTransfer(), bubbles: true, cancelable: true,
                    }));
                }
            JS);
            $b->pause(500);

            $this->assertTrue($this->readLeftCollapsed($b),
                'Rail should auto-close on phone when a palette drag begins');
        });
    }

    public function test_tapping_a_palette_item_does_NOT_close_the_rail_on_desktop(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(400);
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                wire.set('blocks', []);
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                Alpine.$data(root).leftCollapsed = false;
            JS);
            $b->pause(400);

            $b->script(<<<'JS'
                const btn = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Heading'));
                if (btn) btn.click();
            JS);
            $b->pause(600);

            $this->assertFalse($this->readLeftCollapsed($b),
                'Rail should stay open on desktop · auto-close is mobile-only');
        });
    }
}
