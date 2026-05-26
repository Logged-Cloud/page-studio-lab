<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Drag a variable chip from the bottom strip directly onto a block in
 * the canvas. While hovering, the block shows a caret-style indicator
 * marking the insertion point; on drop the variable token is spliced
 * into the block's primary text setting at that point.
 *
 * Two paths are tested · the Alpine-level handler (drives the same
 * code path as a real drop) and an integration check that the
 * server method updates blocks[*]['settings']['text'].
 */
class DragVarIntoBlockTest extends DuskTestCase
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
            wire.set('blocks', [
                { id: 'h1', type: 'heading', settings: { text: 'Hello', level: 'h1', align: 'left' } },
            ]);
        JS);
        $b->pause(600);
    }

    public function test_block_exposes_a_drop_handler_for_var_chips(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $has = $b->script(<<<'JS'
                const wrap = document.querySelector('.ps-pb-block-wrap[data-block-path="0"]');
                if (! wrap) return null;
                // The wrap should declare it's a var-drop target via a
                // data-attr or a x-on:drop listener that responds to the
                // var dataTransfer payload.
                return {
                    hasDataAttr: wrap.dataset.acceptVarDrop === 'true' || wrap.hasAttribute('data-accept-var-drop'),
                    hasListener: typeof wrap.onDropVar === 'function' || !! wrap.getAttribute('@drop'),
                };
            JS)[0];

            $this->assertNotNull($has, '.ps-pb-block-wrap should exist for the heading block');
            $this->assertTrue(($has['hasDataAttr'] ?? false) || ($has['hasListener'] ?? false),
                'Block wrap should signal it accepts var drops · got '.json_encode($has));
        });
    }

    public function test_dragging_var_chip_over_block_shows_caret_indicator(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            // Fire dragstart on the userId var chip, then dragover on the
            // heading block at a specific point. The block-editor should
            // mount a caret indicator child element while the drag is in
            // flight.
            $b->script(<<<'JS'
                const chip = document.querySelector('.ps-pb-var-strip [data-var-name="userId"]');
                const block = document.querySelector('.ps-pb-block-wrap[data-block-path="0"]');
                const dt = new DataTransfer();
                dt.setData('text/plain', '{{ userId }}');
                dt.setData('application/x-page-studio-var', 'userId');
                chip.dispatchEvent(new DragEvent('dragstart', { dataTransfer: dt, bubbles: true, cancelable: true }));

                const r = block.getBoundingClientRect();
                block.dispatchEvent(new DragEvent('dragover', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                    clientX: r.left + 30, clientY: r.top + 25,
                }));
            JS);
            $b->pause(400);

            $hasCaret = $b->script(<<<'JS'
                return !! document.querySelector('.ps-pb-var-drop-caret');
            JS)[0];

            $this->assertTrue((bool) $hasCaret,
                '.ps-pb-var-drop-caret should be visible while a var chip is being dragged over a block');
        });
    }

    public function test_dropping_var_chip_onto_a_list_block_inserts_into_items(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            // Replace with a list block at root.
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).set('blocks', [
                    { id: 'l1', type: 'list', settings: { items: "Apples\nBananas\nCarrots", style: 'bullet' } },
                ]);
            JS);
            $b->pause(600);

            $b->script(<<<'JS'
                const chip = document.querySelector('.ps-pb-var-strip [data-var-name="userId"]');
                const block = document.querySelector('.ps-pb-block-wrap[data-block-path="0"]');
                const dt = new DataTransfer();
                dt.setData('text/plain', '{{ userId }}');
                dt.setData('application/x-page-studio-var', 'userId');
                chip.dispatchEvent(new DragEvent('dragstart', { dataTransfer: dt, bubbles: true, cancelable: true }));
                const r = block.getBoundingClientRect();
                block.dispatchEvent(new DragEvent('drop', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                    clientX: r.left + 40, clientY: r.top + 25,
                }));
            JS);
            $b->pause(900);

            $items = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                    .get('blocks')[0].settings.items;
            JS)[0];

            $this->assertStringContainsString('{{ userId }}', (string) $items,
                'list block items should contain the var token after drop · got '.var_export($items, true));
        });
    }

    public function test_dropping_var_chip_onto_block_nested_in_columns_left_slot(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).set('blocks', [
                    {
                        id: 'c1', type: 'columns', settings: { ratio: '1-1' },
                        children: {
                            left:  [{ id: 'inner-list', type: 'list', settings: { items: "One\nTwo", style: 'bullet' } }],
                            right: [],
                        },
                    },
                ]);
            JS);
            $b->pause(700);

            $b->script(<<<'JS'
                const chip = document.querySelector('.ps-pb-var-strip [data-var-name="userId"]');
                // The nested list block lives at path "0/left/0".
                const inner = document.querySelector('.ps-pb-block-wrap[data-block-path="0/left/0"]');
                const dt = new DataTransfer();
                dt.setData('text/plain', '{{ userId }}');
                dt.setData('application/x-page-studio-var', 'userId');
                chip.dispatchEvent(new DragEvent('dragstart', { dataTransfer: dt, bubbles: true, cancelable: true }));
                const r = inner.getBoundingClientRect();
                inner.dispatchEvent(new DragEvent('drop', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                    clientX: r.left + 20, clientY: r.top + 20,
                }));
            JS);
            $b->pause(900);

            $items = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                    .get('blocks')[0].children.left[0].settings.items;
            JS)[0];

            $this->assertStringContainsString('{{ userId }}', (string) $items,
                'nested list block (inside columns left slot) should accept var drop · got '.var_export($items, true));
        });
    }

    public function test_var_drop_on_list_item_lands_in_the_correct_line(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).set('blocks', [
                    { id: 'l1', type: 'list', settings: { items: "Apples\nBananas\nCarrots", style: 'bullet' } },
                ]);
            JS);
            $b->pause(700);

            // Drop the var chip over the SECOND <li> (Bananas). The caret
            // visually indicated the second line; the inserted token must
            // appear inside the "Bananas" line, not at the end of the
            // entire items string.
            $b->script(<<<'JS'
                const chip = document.querySelector('.ps-pb-var-strip [data-var-name="userId"]');
                const lis = document.querySelectorAll('.ps-pb-block-wrap[data-block-path="0"] li');
                const target = lis[1]; // Bananas
                const dt = new DataTransfer();
                dt.setData('text/plain', '{{ userId }}');
                dt.setData('application/x-page-studio-var', 'userId');
                chip.dispatchEvent(new DragEvent('dragstart', { dataTransfer: dt, bubbles: true, cancelable: true }));

                const r = target.getBoundingClientRect();
                const cx = r.left + 25, cy = r.top + r.height / 2;
                const wrap = target.closest('.ps-pb-block-wrap');
                wrap.dispatchEvent(new DragEvent('dragover', {
                    dataTransfer: dt, bubbles: true, cancelable: true, clientX: cx, clientY: cy,
                }));
                wrap.dispatchEvent(new DragEvent('drop', {
                    dataTransfer: dt, bubbles: true, cancelable: true, clientX: cx, clientY: cy,
                }));
            JS);
            $b->pause(900);

            $items = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                    .get('blocks')[0].settings.items;
            JS)[0];

            $lines = explode("\n", (string) $items);
            // Assert the token landed INSIDE the Bananas line at a caret
            // position. Stripping the token from line[1] must restore
            // "Bananas" exactly, proving the caret-to-source-offset mapping
            // respects line boundaries (the bug was: text-node offset 3 in
            // "Bananas" was being applied to source "Apples\nBananas..."
            // and landing inside Apples).
            $this->assertCount(3, $lines, 'items should still be 3 lines · got '.var_export($items, true));
            $this->assertSame('Apples', $lines[0],
                'first line (Apples) should stay clean · got '.var_export($items, true));
            $this->assertStringContainsString('{{ userId }}', $lines[1],
                'second line should carry the inserted token · got '.var_export($items, true));
            $this->assertSame('Bananas', str_replace('{{ userId }}', '', $lines[1]),
                'second line minus the token should be Bananas · got '.var_export($items, true));
            $this->assertSame('Carrots', $lines[2],
                'third line (Carrots) should stay clean · got '.var_export($items, true));
        });
    }

    public function test_var_drop_on_code_block_lands_at_caret_position(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).set('blocks', [
                    { id: 'c1', type: 'code', settings: { code: "alpha\nbeta\ngamma", language: 'plain' } },
                ]);
            JS);
            $b->pause(700);

            // Drop right at the start of "beta" (offset 6 = after "alpha\n").
            // The code block renders source verbatim inside one <code>
            // text node so caret offset maps 1:1 to source offset.
            $b->script(<<<'JS'
                const chip = document.querySelector('.ps-pb-var-strip [data-var-name="userId"]');
                const codeEl = document.querySelector('.ps-pb-block-wrap[data-block-path="0"] pre code');
                const textNode = codeEl.firstChild;
                const range = document.createRange();
                range.setStart(textNode, 6);
                range.setEnd(textNode, 6);
                const r = range.getBoundingClientRect();

                const dt = new DataTransfer();
                dt.setData('text/plain', '{{ userId }}');
                dt.setData('application/x-page-studio-var', 'userId');
                chip.dispatchEvent(new DragEvent('dragstart', { dataTransfer: dt, bubbles: true, cancelable: true }));

                const wrap = codeEl.closest('.ps-pb-block-wrap');
                wrap.dispatchEvent(new DragEvent('dragover', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                    clientX: r.left + 1, clientY: r.top + r.height / 2,
                }));
                wrap.dispatchEvent(new DragEvent('drop', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                    clientX: r.left + 1, clientY: r.top + r.height / 2,
                }));
            JS);
            $b->pause(900);

            $text = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                    .get('blocks')[0].settings.code;
            JS)[0];

            // Expect: token sits at the start of line 2 ("beta"). Lines
            // 1 and 3 stay clean.
            $lines = explode("\n", (string) $text);
            $this->assertCount(3, $lines, 'code source should still be 3 lines · got '.var_export($text, true));
            $this->assertSame('alpha', $lines[0],
                'line 1 (alpha) should stay clean · got '.var_export($text, true));
            $this->assertStringContainsString('{{ userId }}', $lines[1],
                'line 2 should carry the inserted token · got '.var_export($text, true));
            $this->assertSame('beta', str_replace('{{ userId }}', '', $lines[1]),
                'line 2 minus the token should be beta · got '.var_export($text, true));
            $this->assertSame('gamma', $lines[2],
                'line 3 (gamma) should stay clean · got '.var_export($text, true));
        });
    }

    public function test_var_drop_on_paragraph_block_lands_at_caret_position(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).set('blocks', [
                    { id: 'p1', type: 'paragraph', settings: { text: 'Hello world', align: 'left' } },
                ]);
            JS);
            $b->pause(700);

            // Drop right after "Hello " (offset 6 in the single text node).
            $b->script(<<<'JS'
                const chip = document.querySelector('.ps-pb-var-strip [data-var-name="userId"]');
                const p    = document.querySelector('.ps-pb-block-wrap[data-block-path="0"] p, .ps-pb-block-wrap[data-block-path="0"] .ps-pb-block-render');
                const textNode = (function find(n) {
                    if (n.nodeType === 3 && (n.textContent || '').trim().length) return n;
                    for (const c of n.childNodes) { const f = find(c); if (f) return f; }
                    return null;
                })(p);
                const range = document.createRange();
                range.setStart(textNode, 6);
                range.setEnd(textNode, 6);
                const r = range.getBoundingClientRect();

                const dt = new DataTransfer();
                dt.setData('text/plain', '{{ userId }}');
                dt.setData('application/x-page-studio-var', 'userId');
                chip.dispatchEvent(new DragEvent('dragstart', { dataTransfer: dt, bubbles: true, cancelable: true }));

                const wrap = p.closest('.ps-pb-block-wrap');
                wrap.dispatchEvent(new DragEvent('dragover', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                    clientX: r.left + 1, clientY: r.top + r.height / 2,
                }));
                wrap.dispatchEvent(new DragEvent('drop', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                    clientX: r.left + 1, clientY: r.top + r.height / 2,
                }));
            JS);
            $b->pause(900);

            $text = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                    .get('blocks')[0].settings.text;
            JS)[0];

            $this->assertSame('Hello {{ userId }}world', (string) $text,
                'paragraph text should have the token inserted between Hello and world · got '.var_export($text, true));
        });
    }

    public function test_visual_caret_while_dragging_var_over_block(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->script(<<<'JS'
                const chip = document.querySelector('.ps-pb-var-strip [data-var-name="userId"]');
                const block = document.querySelector('.ps-pb-block-wrap[data-block-path="0"]');
                const dt = new DataTransfer();
                dt.setData('text/plain', '{{ userId }}');
                dt.setData('application/x-page-studio-var', 'userId');
                chip.dispatchEvent(new DragEvent('dragstart', { dataTransfer: dt, bubbles: true, cancelable: true }));
                const r = block.getBoundingClientRect();
                block.dispatchEvent(new DragEvent('dragover', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                    clientX: r.left + 35, clientY: r.top + 30,
                }));
            JS);
            $b->pause(400);
            $b->screenshot('var-drag-caret-over-block');
        });
    }

    public function test_dropping_var_chip_inserts_token_into_block_text(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $b->script(<<<'JS'
                const chip = document.querySelector('.ps-pb-var-strip [data-var-name="userId"]');
                const block = document.querySelector('.ps-pb-block-wrap[data-block-path="0"]');
                const dt = new DataTransfer();
                dt.setData('text/plain', '{{ userId }}');
                dt.setData('application/x-page-studio-var', 'userId');
                chip.dispatchEvent(new DragEvent('dragstart', { dataTransfer: dt, bubbles: true, cancelable: true }));
                const r = block.getBoundingClientRect();
                block.dispatchEvent(new DragEvent('drop', {
                    dataTransfer: dt, bubbles: true, cancelable: true,
                    clientX: r.left + 40, clientY: r.top + 25,
                }));
            JS);
            $b->pause(900);

            $text = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                    .get('blocks')[0].settings.text;
            JS)[0];

            $this->assertStringContainsString('{{ userId }}', (string) $text,
                'Block text setting should contain the var token after drop · got '.var_export($text, true));
        });
    }
}
