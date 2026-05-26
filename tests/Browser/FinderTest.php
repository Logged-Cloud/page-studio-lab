<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * In-page finder · Ctrl-F / `/` opens a palette over the editor that
 * searches the block tree + node graph by type or settings text.
 */
class FinderTest extends DuskTestCase
{
    protected function freshBlocks(Browser $b): void
    {
        $b->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('blocks', []);");
        $b->pause(200);
        $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes', []);");
        $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('edges', []);");
        $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).call('clearSelection');");
        $b->pause(200);
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

    public function test_slash_key_opens_the_finder(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);
            $b->script("document.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true }));");
            $b->pause(300);

            $visible = $b->script("return document.querySelector('.ps-pb-find')?.offsetParent !== null;")[0];
            $this->assertTrue($visible, 'Finder overlay should be visible after pressing /');
        });
    }

    public function test_finder_lists_a_matching_block_and_jumps_on_click(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);
            $this->lwCall($b, 'addBlock', 'heading');
            $b->pause(400);
            // Override the heading text so we have something specific to find.
            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('blocks.0.settings.text', 'Hello Findable');");
            $b->pause(400);

            $b->script("document.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true }));");
            $b->pause(250);
            $b->type('.ps-pb-find-input', 'Findable')->pause(300);

            $count = $b->script("return document.querySelectorAll('.ps-pb-find-row').length;")[0];
            $this->assertGreaterThanOrEqual(1, $count, 'Finder should list at least one match for `Findable`');

            // Click first result · should select the heading on the canvas.
            $b->script("document.querySelector('.ps-pb-find-row').click();");
            $b->pause(500);

            $selectedPath = $this->readProp($b, 'selectedPath');
            $this->assertSame('0', $selectedPath, 'Clicking a block result should select that block');
        });
    }

    public function test_finder_lists_a_matching_node_and_jumps_on_click(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshBlocks($b);
            $this->lwCall($b, 'addNode', 'source.constant');
            $b->pause(400);
            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes.0.settings.value', 'lookup_token');");
            $b->pause(400);

            $b->script("document.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true }));");
            $b->pause(250);
            $b->type('.ps-pb-find-input', 'lookup_token')->pause(300);

            $count = $b->script("return document.querySelectorAll('.ps-pb-find-row').length;")[0];
            $this->assertGreaterThanOrEqual(1, $count, 'Finder should match nodes by setting value');

            $b->script("document.querySelector('.ps-pb-find-row').click();");
            $b->pause(500);

            $selectedNodeId = $this->readProp($b, 'selectedNodeId');
            $this->assertNotNull($selectedNodeId, 'Clicking a node result should select that node');
        });
    }
}
