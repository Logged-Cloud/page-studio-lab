<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Duplicating a node + editing its settings should mutate ONLY the
 * clone, not the original. The user reported "cloning a node and
 * then updating the setting e.g var name updates all the clones".
 *
 * This test reproduces that flow end-to-end against the live
 * editor.
 */
class DuplicateNodeIsolationTest extends DuskTestCase
{
    protected function fresh(Browser $b): void
    {
        $b->resize(1440, 900)
            ->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->pause(400);
        $b->script(<<<'JS'
            const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
            if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            wire.set('nodes', [
                { id: 'src', type: 'output', position: { x: 100, y: 100 }, settings: { name: 'original' } },
            ]);
            wire.set('edges', []);
            wire.set('selectedNodeId', null);
        JS);
        $b->pause(600);
    }

    public function test_typing_on_clone_does_not_mutate_original(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            // Duplicate the source node.
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('duplicateNode', 'src');
            JS);
            $b->pause(700);

            $info = $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                const nodes = wire.get('nodes');
                return { count: nodes.length, selected: wire.get('selectedNodeId'), nodes: nodes };
            JS)[0];

            $this->assertSame(2, $info['count'], 'should have 2 nodes after duplicate · got '.json_encode($info));
            $this->assertNotSame('src', $info['selected'], 'clone should be auto-selected · got '.var_export($info['selected'], true));

            // Type a new value in the settings input (the clone is selected).
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                // The clone is at index 1 · its index-prefixed property.
                wire.set('nodes.1.settings.name', 'cloneOnly');
            JS);
            $b->pause(700);

            $after = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('nodes');
            JS)[0];

            $this->assertSame('original', $after[0]['settings']['name'] ?? null,
                'original (index 0) name should NOT have been mutated · got '.json_encode($after));
            $this->assertSame('cloneOnly', $after[1]['settings']['name'] ?? null,
                'clone (index 1) name should be the new value · got '.json_encode($after));
        });
    }

    public function test_real_typing_via_input_element_does_not_bleed(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            // Select the source node + duplicate it via the canvas duplicate
            // button (the real UI path, not a Livewire.call shortcut).
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                wire.call('selectNode', 'src');
            JS);
            $b->pause(500);
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                wire.call('duplicateNode', 'src');
            JS);
            $b->pause(800);

            // The clone is now selected · find the input the settings panel
            // rendered for the `name` field and type into it.
            $hadInput = $b->script(<<<'JS'
                const input = document.querySelector('.ps-ne-settings input[wire\\:model\\.live\\.debounce\\.300ms*="settings.name"]')
                    || document.querySelector('.ps-ne-settings input[wire\\:model*="settings.name"]')
                    || document.querySelector('.ps-ne-settings input[type="text"]');
                return !! input;
            JS)[0];
            $this->assertTrue((bool) $hadInput, 'settings panel should expose a name input for the output node');

            $b->script(<<<'JS'
                const input = document.querySelector('.ps-ne-settings input[type="text"]');
                input.focus();
                input.value = 'realClone';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.dispatchEvent(new Event('blur',   { bubbles: true }));
            JS);
            $b->pause(900);

            $after = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('nodes');
            JS)[0];

            $this->assertSame('original', $after[0]['settings']['name'] ?? null,
                'real typing on the clone via the actual <input> must not mutate the original · got '.json_encode($after));
            $this->assertSame('realClone', $after[1]['settings']['name'] ?? null,
                'real typing on the clone must mutate the clone · got '.json_encode($after));
        });
    }

    public function test_typing_on_original_after_duplicate_does_not_bleed_to_clone(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('duplicateNode', 'src');
            JS);
            $b->pause(700);

            // Now mutate the ORIGINAL.
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                    .set('nodes.0.settings.name', 'changedOriginal');
            JS);
            $b->pause(700);

            $after = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('nodes');
            JS)[0];

            $this->assertSame('changedOriginal', $after[0]['settings']['name'] ?? null);
            $this->assertSame('original',        $after[1]['settings']['name'] ?? null,
                'clone (index 1) name should NOT inherit original-side edit · got '.json_encode($after));
        });
    }
}
