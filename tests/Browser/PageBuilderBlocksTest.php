<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Block-authoring side of the page-studio · mirrors the helper pattern from
 * NodeEditorTest so the two harnesses read alike. All tests target route id
 * 3 (dusk.test). Each test wipes the block tree via Livewire so order doesn't
 * matter.
 */
class PageBuilderBlocksTest extends DuskTestCase
{
    /**
     * Open the builder and clear the block tree · the blocks side of the
     * `fresh($b)` helper from NodeEditorTest. Also clears the selection so
     * the right-rail settings panel starts collapsed.
     */
    protected function freshBlocks(Browser $b): void
    {
        $b->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]')
            ->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('blocks', []);");
        $b->pause(300);
        $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).call('clearSelection');");
        $b->pause(300);
    }

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

    /** @test */
    public function test_clicking_a_palette_block_drops_it_on_the_canvas(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            // The palette renders one button per block type · find the
            // Heading button by its visible text and click it. The button's
            // own text content carries the icon + label, so we filter on
            // contains('Heading') rather than equality.
            $b->script(<<<'JS'
                const btn = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Heading'));
                if (btn) btn.click();
            JS);
            $b->pause(500);

            $blocks = $this->readProp($b, 'blocks');
            $this->assertCount(1, $blocks, 'Palette click should add exactly one block · got: '.json_encode($blocks));
            $this->assertSame('heading', $blocks[0]['type'] ?? null,
                'Heading palette item should add a heading block · got: '.json_encode($blocks));
        });
    }

    /** @test */
    public function test_dragging_a_palette_block_onto_the_canvas_inserts_it(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            // The page-builder's drag handlers shuttle state through Alpine
            // scope props (`dragKind` / `dragPayload`) rather than the
            // dataTransfer payload, so a plain DragEvent on the canvas won't
            // arrive carrying a type. Drive the Alpine scope directly · this
            // is the same path a real palette-drag would hit (dragstart sets
            // those props, drop reads them).
            $b->script(<<<'JS'
                const paletteItem = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Paragraph'));
                const scope = Alpine.$data(paletteItem.closest('[x-data]'));
                const dt = new DataTransfer();
                scope.onPaletteDragStart({ dataTransfer: dt, preventDefault(){}, stopPropagation(){} }, 'paragraph');
                const canvas = document.querySelector('.ps-pb-canvas');
                const canvasScope = Alpine.$data(canvas.closest('[x-data]'));
                canvasScope.onCanvasDragOver({ dataTransfer: dt, preventDefault(){} });
                canvasScope.onCanvasDrop({ dataTransfer: dt, preventDefault(){} });
            JS);
            $b->pause(600);

            $blocks = $this->readProp($b, 'blocks');
            $this->assertNotEmpty($blocks, 'Drag-drop should add a block · got: '.json_encode($blocks));
            $types = array_column($blocks, 'type');
            $this->assertContains('paragraph', $types,
                'Drag-drop should land a paragraph block · got: '.json_encode($types));
        });
    }

    /** @test */
    public function test_settings_panel_round_trips_a_block_text_change(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            $this->lwCall($b, 'addBlock', 'heading');
            $b->pause(400);
            $blocks = $this->readProp($b, 'blocks');
            $this->assertCount(1, $blocks);

            // Root index 0 · path is just '0'.
            $this->lwCall($b, 'selectBlock', '0');
            $b->pause(300);

            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('blocks.0.settings.text', 'Hello world');");
            // Autosave is debounced ~600ms · wait through it before reading.
            $b->pause(1200);

            $blocks = $this->readProp($b, 'blocks');
            $this->assertSame('Hello world', $blocks[0]['settings']['text'] ?? null,
                'Settings round-trip should land the new text on the block · got: '.json_encode($blocks));
        });
    }

    /** @test */
    public function test_nesting_a_block_into_a_section_slot(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            // Add a section · layout block with a `body` slot.
            $this->lwCall($b, 'addBlock', 'section');
            $b->pause(700);
            $afterSection = $this->readProp($b, 'blocks');
            $this->assertCount(1, $afterSection, 'Section should land at root · got: '.json_encode($afterSection));

            // addBlock(type, parentPath, slot, index) · drop a paragraph into
            // the section's body slot. parentPath = '0' (root index 0).
            $this->lwCall($b, 'addBlock', 'paragraph', '0', 'body', 0);
            $b->pause(700);

            $blocks = $this->readProp($b, 'blocks');
            $this->assertCount(1, $blocks, 'Should still be exactly one root block · got: '.json_encode($blocks));
            $this->assertSame('section', $blocks[0]['type'] ?? null);
            $body = $blocks[0]['children']['body'] ?? [];
            $this->assertCount(1, $body, 'Section body slot should hold one child · got: '.json_encode($body));
            $this->assertSame('paragraph', $body[0]['type'] ?? null,
                'Nested child should be a paragraph · got: '.json_encode($body));
        });
    }

    public function test_palette_dragstart_writes_the_payload_to_dataTransfer(): void
    {
        // Regression · Firefox refuses to start an HTML5 drag if dragstart
        // doesn't call dataTransfer.setData(), so the user clicks the
        // palette button, mouse moves, no drag begins, slot drop never
        // fires. Chrome was more forgiving but cross-scope reads also lose
        // the payload without setData. Locks in the setData call.
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            $payload = $b->script(<<<'JS'
                const paletteItem = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Heading'));
                const dt = new DataTransfer();
                paletteItem.dispatchEvent(new DragEvent('dragstart', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                }));
                return {
                    text:    dt.getData('text/plain'),
                    custom:  dt.getData('application/x-page-studio'),
                };
            JS)[0];

            $this->assertSame('ps-pb-palette:heading', $payload['text'] ?? null,
                'Palette dragstart should write `ps-pb-palette:<type>` to text/plain · got: '.json_encode($payload));
            $this->assertNotEmpty($payload['custom'] ?? '',
                'Palette dragstart should also write the rich application/x-page-studio payload · got: '.json_encode($payload));
        });
    }

    public function test_dragging_a_heading_into_a_columns_slot_that_already_has_a_child(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            $this->lwCall($b, 'addBlock', 'columns');
            $b->pause(500);
            // Pre-fill the LEFT slot with a paragraph so the slot already has
            // a child block · the recursion in _block-editor.blade.php wraps
            // every kid in a draggable div whose own dragover/drop fires
            // BEFORE the slot's, and we want to make sure the new heading
            // still lands inside the slot (above or below the existing kid)
            // and NOT at the root.
            $this->lwCall($b, 'addBlock', 'paragraph', '0', 'left', 0);
            $b->pause(500);

            // Drive the drag via Alpine scope · the slot-empty case already
            // passes, so this exercises the populated-slot path.
            $b->script(<<<'JS'
                const root  = document.querySelector('[x-data="pageStudioPageBuilder()"]') || document.querySelector('[data-component="page-studio.page-builder"]');
                const scope = Alpine.$data(root);
                const dt = new DataTransfer();
                const evMock = { dataTransfer: dt, preventDefault(){}, stopPropagation(){}, clientY: 0, currentTarget: { getBoundingClientRect(){ return { top: 0, height: 40 }; } } };
                scope.onPaletteDragStart(evMock, 'heading');
                // Mouse enters the slot · onSlotDragOver fires
                scope.onSlotDragOver(evMock, '0', 'left', 1);
                // Then mouse moves over the existing paragraph child ·
                // onBlockDragOver fires with the kid's parentPath/slot/index.
                // The kid is at path "0/left/0" · its parentPath is "0/left",
                // its slot key is "left" within the parent, its index is 0.
                scope.onBlockDragOver(evMock, '0', 'left', 0);
                // User releases on the kid block.
                scope.onBlockDrop(evMock, '0', 'left', 0);
            JS);
            $b->pause(700);

            $blocks = $this->readProp($b, 'blocks');
            $left = $blocks[0]['children']['left'] ?? [];
            $this->assertCount(2, $left,
                'Slot should now hold 2 children · got: '.json_encode($blocks[0]['children'] ?? null));
            $types = array_column($left, 'type');
            $this->assertContains('heading', $types,
                'Heading should land inside the slot · got: '.json_encode($types));
            $this->assertNotEquals(2, count($blocks),
                'Heading should NOT have leaked out to the root · got: '.json_encode(array_column($blocks, 'type')));
        });
    }

    public function test_dragging_a_heading_into_a_columns_left_slot_via_native_drag_events(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);
            $this->lwCall($b, 'addBlock', 'columns');
            $b->pause(600);

            // Use real DragEvent objects · this is the path that exercises
            // the browser-level dataTransfer plumbing. If this fails but the
            // alpine-level test passes, the bug is in our dragstart handler
            // (likely missing setData) or in event-bubbling between the
            // palette dragstart and the slot drop.
            $b->script(<<<'JS'
                const paletteItem = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Heading'));
                const slot = document.querySelector('.ps-pb-slot[data-slot="left"]');
                const dt = new DataTransfer();

                paletteItem.dispatchEvent(new DragEvent('dragstart', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                }));
                slot.dispatchEvent(new DragEvent('dragenter', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                }));
                slot.dispatchEvent(new DragEvent('dragover', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                }));
                slot.dispatchEvent(new DragEvent('drop', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                }));
            JS);
            $b->pause(700);

            $blocks = $this->readProp($b, 'blocks');
            $left = $blocks[0]['children']['left'] ?? [];
            $this->assertCount(1, $left,
                'Heading native-drag should land in the columns left slot · got: '.json_encode($blocks[0]['children'] ?? null));
            $this->assertSame('heading', $left[0]['type'] ?? null);
        });
    }

    public function test_dragging_a_heading_into_a_columns_left_slot_via_alpine_drops_it_there(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            // Seed a 2-columns layout block via the same path the palette
            // click would take · then exercise the real drag flow over its
            // left slot.
            $this->lwCall($b, 'addBlock', 'columns');
            $b->pause(600);

            $blocks = $this->readProp($b, 'blocks');
            $this->assertCount(1, $blocks, 'Columns should land at root');
            $this->assertSame('columns', $blocks[0]['type'] ?? null);

            // Drive the drag via the page-builder Alpine scope · matches the
            // path a real palette drag would take (dragstart writes dragKind/
            // dragPayload, slot dragover updates dropTarget, slot drop fires
            // commitDrop). This isolates the headless DnD plumbing.
            $b->script(<<<'JS'
                const root  = document.querySelector('[x-data="pageStudioPageBuilder()"]') || document.querySelector('[data-component="page-studio.page-builder"]');
                const scope = Alpine.$data(root);

                const paletteItem = Array.from(document.querySelectorAll('.ps-pb-palette .ps-pb-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Heading'));
                const slot = document.querySelector('.ps-pb-slot[data-slot="left"]');

                const dt = new DataTransfer();
                const evMock = { dataTransfer: dt, preventDefault(){}, stopPropagation(){} };

                scope.onPaletteDragStart(evMock, 'heading');
                scope.onSlotDragOver(evMock, '0', 'left', 0);
                scope.onSlotDrop(evMock, '0', 'left');
            JS);
            $b->pause(700);

            $blocks = $this->readProp($b, 'blocks');
            $this->assertCount(1, $blocks, 'Should still be exactly one root block · got: '.json_encode($blocks));
            $this->assertSame('columns', $blocks[0]['type'] ?? null);
            $left = $blocks[0]['children']['left'] ?? [];
            $this->assertCount(1, $left,
                'Heading drag should land in the columns left slot · got: '.json_encode($blocks[0]['children'] ?? null));
            $this->assertSame('heading', $left[0]['type'] ?? null);
        });
    }

    /** @test */
    public function test_remove_block_drops_it_from_the_tree(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            $this->lwCall($b, 'addBlock', 'heading');
            $b->pause(300);
            $this->assertCount(1, $this->readProp($b, 'blocks'));

            $this->lwCall($b, 'removeBlock', '0');
            $b->pause(300);
            $this->assertSame([], $this->readProp($b, 'blocks'),
                'removeBlock should empty the tree');
        });
    }

    /** @test */
    public function test_undo_restores_a_removed_block(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            $this->lwCall($b, 'addBlock', 'heading');
            $b->pause(300);
            $this->lwCall($b, 'addBlock', 'paragraph');
            $b->pause(300);
            $this->assertCount(2, $this->readProp($b, 'blocks'));

            $this->lwCall($b, 'removeBlock', '1');
            $b->pause(300);
            $this->assertCount(1, $this->readProp($b, 'blocks'),
                'After removing root index 1 there should be one block left');

            $this->lwCall($b, 'undo');
            $b->pause(400);
            $blocks = $this->readProp($b, 'blocks');
            $this->assertCount(2, $blocks,
                'Undo should restore the removed paragraph · got: '.json_encode($blocks));
        });
    }
}
