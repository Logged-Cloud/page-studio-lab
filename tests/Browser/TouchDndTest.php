<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Touch DnD · HTML5 DragEvent doesn't fire on touch devices, so the
 * page-builder ships a parallel pointer-events path. These tests drive
 * synthetic PointerEvents with pointerType='touch' to verify the long-
 * press → drag → drop flow lands a block at the right place.
 */
class TouchDndTest extends DuskTestCase
{
    protected function lwCall(Browser $b, string $method, ...$args): void
    {
        $jsonArgs = json_encode($args);
        $b->script(
            "Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).call('$method', ...$jsonArgs);"
        );
    }

    protected function readProp(Browser $b, string $prop): mixed
    {
        return $b->script(
            "return Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).get('$prop');"
        )[0];
    }

    protected function freshBlocks(Browser $b): void
    {
        $b->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('blocks', []);");
        $b->pause(200);
    }

    public function test_touch_long_press_on_a_palette_item_then_drop_on_canvas_adds_a_block(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);
            $b->resize(390, 844)->pause(300);

            // The page-builder root Alpine scope holds startTouchDrag.
            // Dispatch a touch pointerdown on the Heading palette item,
            // wait past the 220ms long-press threshold, then a pointerup
            // on the canvas.
            $b->script(<<<'JS'
                const palette = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Heading'));
                const canvas = document.querySelector('.ps-pb-canvas');
                const cr = canvas.getBoundingClientRect();
                const targetX = cr.left + cr.width / 2;
                const targetY = cr.top + 80;

                const make = (type, target, x, y, extra = {}) => new PointerEvent(type, Object.assign({
                    bubbles: true, cancelable: true, pointerType: 'touch', pointerId: 1, clientX: x, clientY: y,
                }, extra));

                palette.dispatchEvent(make('pointerdown', palette, 50, 50));
                // Wait past the long-press threshold (220ms).
                window.__touchDrop = () => {
                    window.dispatchEvent(make('pointermove', null, targetX, targetY));
                    window.dispatchEvent(make('pointerup',   null, targetX, targetY));
                };
                setTimeout(window.__touchDrop, 280);
            JS);
            $b->pause(900);

            $blocks = $this->readProp($b, 'blocks');
            $this->assertNotEmpty($blocks, 'Touch drag from palette should add a block · got: '.json_encode($blocks));
            $this->assertSame('heading', $blocks[0]['type'] ?? null);
        });
    }

    public function test_short_touch_tap_falls_through_to_the_click_handler_for_tap_to_add(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);
            $b->resize(390, 844)->pause(300);

            // pointerdown immediately followed by pointerup with no drag
            // gesture · the long-press timer never fires, so the cancel
            // path runs and the existing @click="$wire.addBlock(...)"
            // adds the block at root via Livewire.
            $b->script(<<<'JS'
                const palette = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Heading'));
                const make = (type, target, x, y) => new PointerEvent(type, {
                    bubbles: true, cancelable: true, pointerType: 'touch', pointerId: 2, clientX: x, clientY: y,
                });
                palette.dispatchEvent(make('pointerdown', palette, 50, 50));
                setTimeout(() => {
                    window.dispatchEvent(make('pointerup', null, 50, 50));
                    palette.click();
                }, 50);
            JS);
            $b->pause(900);

            $blocks = $this->readProp($b, 'blocks');
            $this->assertNotEmpty($blocks, 'Tap-to-add should still work alongside touch DnD · got: '.json_encode($blocks));
            $this->assertSame('heading', $blocks[0]['type'] ?? null);
        });
    }

    public function test_touch_drag_can_drop_a_block_into_a_section_slot(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);
            $b->resize(390, 844)->pause(300);
            $this->lwCall($b, 'addBlock', 'section');
            $b->pause(400);

            $b->script(<<<'JS'
                const palette = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Paragraph'));
                const slot = document.querySelector('.ps-pb-slot[data-slot="body"]');
                const sr = slot.getBoundingClientRect();
                const tx = sr.left + sr.width / 2;
                const ty = sr.top + sr.height / 2;

                const make = (type, x, y) => new PointerEvent(type, {
                    bubbles: true, cancelable: true, pointerType: 'touch', pointerId: 3, clientX: x, clientY: y,
                });
                palette.dispatchEvent(make('pointerdown', 100, 100));
                setTimeout(() => {
                    window.dispatchEvent(make('pointermove', tx, ty));
                    window.dispatchEvent(make('pointerup',   tx, ty));
                }, 280);
            JS);
            $b->pause(900);

            $blocks = $this->readProp($b, 'blocks');
            $body = $blocks[0]['children']['body'] ?? [];
            $this->assertCount(1, $body, 'Touch drag should land in the section body slot · got: '.json_encode($blocks[0]['children'] ?? null));
            $this->assertSame('paragraph', $body[0]['type'] ?? null);
        });
    }
}
