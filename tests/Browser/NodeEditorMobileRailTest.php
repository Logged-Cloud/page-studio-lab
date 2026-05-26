<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Node editor mobile UX · mirrors the page-builder's left-rail pattern:
 *   1. Palette has a toggle button in the drawer header.
 *   2. Palette defaults to collapsed on phone width.
 *   3. Picking a palette node on phone auto-closes the palette so the
 *      canvas is visible to drop the new node.
 *   4. Settings panel is already conditional on selectedNode at the PHP
 *      level · this test asserts the existing contract holds.
 */
class NodeEditorMobileRailTest extends DuskTestCase
{
    protected function freshAtPhoneWidth(Browser $b): void
    {
        $b->resize(390, 844)
            ->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->pause(400);
        // Make sure the node drawer is open.
        $b->script(<<<'JS'
            const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
            if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            wire.set('nodes', []);
            wire.set('edges', []);
            wire.set('selectedNodeId', null);
        JS);
        $b->pause(600);
    }

    protected function readScope(Browser $b, string $prop): mixed
    {
        return $b->script(<<<JS
            const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
            return Alpine.\$data(root).{$prop};
JS)[0];
    }

    public function test_node_palette_defaults_to_collapsed_on_phone(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshAtPhoneWidth($b);

            // Page-builder is open with the node drawer also open. The
            // ne-palette should default to closed at phone width.
            $closed = $this->readScope($b, 'nodePaletteCollapsed');

            $this->assertTrue((bool) $closed,
                'nodePaletteCollapsed should default to true on phone · was: '.var_export($closed, true));
        });
    }

    public function test_node_palette_toggle_button_is_present_in_drawer_header(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshAtPhoneWidth($b);

            $exists = $b->script(<<<'JS'
                return !! document.querySelector('.ps-ne-palette-toggle');
            JS)[0];

            $this->assertTrue((bool) $exists,
                'A .ps-ne-palette-toggle button should exist in the node drawer header');
        });
    }

    public function test_tapping_a_node_palette_item_closes_the_palette_on_phone(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshAtPhoneWidth($b);

            // Open the palette first so we have something to close.
            $b->script(<<<'JS'
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                Alpine.$data(root).nodePaletteCollapsed = false;
            JS);
            $b->pause(300);

            // Tap any palette item (the Constant source node).
            $b->script(<<<'JS'
                const btn = Array.from(document.querySelectorAll('.ps-ne-palette .ps-ne-palette-item'))
                    .find(el => el.textContent.replace(/\s+/g,' ').trim().includes('Constant'));
                if (btn) btn.click();
            JS);
            $b->pause(800);

            $closed = $this->readScope($b, 'nodePaletteCollapsed');
            $this->assertTrue((bool) $closed,
                'Palette should auto-close on phone after a palette tap');

            // And a node should have been added.
            $nodes = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('nodes');
            JS)[0];
            $this->assertGreaterThanOrEqual(1, count($nodes ?? []),
                'Tap should have added a node · got '.json_encode($nodes));
        });
    }

    public function test_node_settings_panel_hides_when_no_node_selected(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshAtPhoneWidth($b);

            // selectedNodeId is null in freshAtPhoneWidth · settings markup
            // is gated by an @if on the server side, so the aside should
            // not be in the DOM at all.
            $exists = $b->script(<<<'JS'
                return !! document.querySelector('.ps-ne-settings');
            JS)[0];

            $this->assertFalse((bool) $exists,
                '.ps-ne-settings should NOT render while selectedNodeId is null');
        });
    }

    public function test_mobile_node_editor_visual(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshAtPhoneWidth($b);

            // Add a couple of nodes via Livewire so the canvas isn't empty.
            $this->lwCall($b, 'addNode', 'source.constant');
            $b->pause(400);
            $this->lwCall($b, 'addNode', 'transform.uppercase');
            $b->pause(400);

            $b->screenshot('mobile-node-editor-collapsed-palette');

            // Open the palette · screenshot the sheet.
            $b->script(<<<'JS'
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                Alpine.$data(root).nodePaletteCollapsed = false;
            JS);
            $b->pause(400);
            $b->screenshot('mobile-node-editor-open-palette');
        });
    }

    protected function lwCall(Browser $b, string $method, ...$args): void
    {
        $jsonArgs = json_encode($args);
        $b->script(
            "Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).call('$method', ...$jsonArgs);"
        );
    }

    public function test_desktop_palette_defaults_to_open(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(400);
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(500);

            $closed = $this->readScope($b, 'nodePaletteCollapsed');
            $this->assertFalse((bool) $closed,
                'On desktop the node palette should default to open · was: '.var_export($closed, true));
        });
    }
}
