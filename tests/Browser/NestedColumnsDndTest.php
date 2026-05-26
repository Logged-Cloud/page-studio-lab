<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Nested-columns drag-drop · proves you can drop a columns block into
 * the slot of another columns block, then drop content into the inner
 * columns' slots. Mirrors the pattern in PageBuilderBlocksTest by
 * driving the Alpine scope's drag handlers directly · same path a real
 * palette-drag walks.
 */
class NestedColumnsDndTest extends DuskTestCase
{
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

    public function test_drag_columns_into_another_columns_slot_via_alpine(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            // Outer columns block.
            $this->lwCall($b, 'addBlock', 'columns');
            $b->pause(600);

            // Drive a palette-drag of a `columns` block into the outer's left slot.
            $b->script(<<<'JS'
                const root  = document.querySelector('[x-data="pageStudioPageBuilder()"]') || document.querySelector('[data-component="page-studio.page-builder"]');
                const scope = Alpine.$data(root);
                const dt = new DataTransfer();
                const evMock = { dataTransfer: dt, preventDefault(){}, stopPropagation(){}, clientY: 0, currentTarget: { getBoundingClientRect(){ return { top: 0, height: 40 }; } } };
                scope.onPaletteDragStart(evMock, 'columns');
                scope.onSlotDragOver(evMock, '0', 'left', 0);
                scope.onSlotDrop(evMock, '0', 'left');
            JS);
            $b->pause(700);

            $blocks = $this->readProp($b, 'blocks');
            $innerLeft = $blocks[0]['children']['left'][0] ?? null;

            $this->assertNotNull($innerLeft,
                'Inner columns should land in outer.left · got: '.json_encode($blocks[0]['children'] ?? null));
            $this->assertSame('columns', $innerLeft['type'] ?? null,
                'Inner block should be a columns block · got: '.json_encode($innerLeft));
        });
    }

    public function test_drag_paragraph_into_a_nested_columns_slot(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            // Outer columns + inner columns nested in outer.left via the
            // server-side addBlock (the previous test proves drag-drop
            // gets us to this shape · this test focuses on what happens
            // when content is dropped INSIDE the nested columns).
            $this->lwCall($b, 'addBlock', 'columns');
            $b->pause(500);
            $this->lwCall($b, 'addBlock', 'columns', '0', 'left', 0);
            $b->pause(500);

            // Drop a paragraph into the inner columns' left slot.
            // Path of inner columns is "0/left/0" · its left slot is the
            // drop target.
            $b->script(<<<'JS'
                const root  = document.querySelector('[x-data="pageStudioPageBuilder()"]') || document.querySelector('[data-component="page-studio.page-builder"]');
                const scope = Alpine.$data(root);
                const dt = new DataTransfer();
                const evMock = { dataTransfer: dt, preventDefault(){}, stopPropagation(){}, clientY: 0, currentTarget: { getBoundingClientRect(){ return { top: 0, height: 40 }; } } };
                scope.onPaletteDragStart(evMock, 'paragraph');
                scope.onSlotDragOver(evMock, '0/left/0', 'left', 0);
                scope.onSlotDrop(evMock, '0/left/0', 'left');
            JS);
            $b->pause(700);

            $blocks = $this->readProp($b, 'blocks');
            $deep = $blocks[0]['children']['left'][0]['children']['left'][0] ?? null;

            $this->assertNotNull($deep,
                'Paragraph should land in outer.left.0.left.0 · got: '.json_encode($blocks[0]['children'] ?? null));
            $this->assertSame('paragraph', $deep['type'] ?? null,
                'Deep child should be a paragraph · got: '.json_encode($deep));
        });
    }

    public function test_nested_columns_render_three_deep_content_in_the_canvas(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);

            // Build a 3-level nested tree by chaining addBlock calls · each
            // call round-trips through the server, so by the time we assert
            // the canvas has all the markup in place. Levels:
            //   outer columns
            //     left   · paragraph LEVEL-0-LEFT
            //     right  · columns-3
            //       left   · paragraph LEVEL-1-A
            //       middle · paragraph LEVEL-1-B
            //       right  · columns
            //         left  · paragraph LEVEL-2-LEFT
            //         right · paragraph LEVEL-2-RIGHT
            $this->lwCall($b, 'addBlock', 'columns');                              $b->pause(500);
            $this->lwCall($b, 'addBlock', 'paragraph', '0', 'left',  0);           $b->pause(400);
            $this->lwCall($b, 'addBlock', 'columns-3', '0', 'right', 0);           $b->pause(400);
            $this->lwCall($b, 'addBlock', 'paragraph', '0/right/0', 'left',   0);  $b->pause(400);
            $this->lwCall($b, 'addBlock', 'paragraph', '0/right/0', 'middle', 0);  $b->pause(400);
            $this->lwCall($b, 'addBlock', 'columns',   '0/right/0', 'right',  0);  $b->pause(400);
            $this->lwCall($b, 'addBlock', 'paragraph', '0/right/0/right/0', 'left',  0); $b->pause(400);
            $this->lwCall($b, 'addBlock', 'paragraph', '0/right/0/right/0', 'right', 0); $b->pause(500);

            // Patch the text on each paragraph via Livewire set so we can
            // assertSeeIn against distinctive needles.
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                const tree = JSON.parse(JSON.stringify(wire.get('blocks')));
                tree[0].children.left[0].settings.text                       = 'LEVEL-0-LEFT';
                tree[0].children.right[0].children.left[0].settings.text     = 'LEVEL-1-A';
                tree[0].children.right[0].children.middle[0].settings.text   = 'LEVEL-1-B';
                tree[0].children.right[0].children.right[0].children.left[0].settings.text  = 'LEVEL-2-LEFT';
                tree[0].children.right[0].children.right[0].children.right[0].settings.text = 'LEVEL-2-RIGHT';
                wire.set('blocks', tree);
            JS);
            $b->pause(800);

            foreach (['LEVEL-0-LEFT','LEVEL-1-A','LEVEL-1-B','LEVEL-2-LEFT','LEVEL-2-RIGHT'] as $needle) {
                $b->assertSeeIn('[data-component="page-studio.page-builder"]', $needle);
            }

            // Resize wide enough for the columns ratios to spread, then snap
            // a screenshot so a human reviewer can eyeball the actual nesting.
            $b->resize(1440, 900)->pause(300);
            $b->screenshot('nested-columns-3-deep');
        });
    }
}
