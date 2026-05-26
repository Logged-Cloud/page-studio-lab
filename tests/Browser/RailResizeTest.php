<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Resizable side panels · the user wants to drag the inner edge of
 * each rail to change its width.
 *
 * Block-builder rails:
 *   .ps-pb-rail--left  has a .ps-pb-rail-grabber on its right edge
 *   .ps-pb-rail--right has a .ps-pb-rail-grabber on its left edge
 *
 * Node editor (inside the drawer):
 *   .ps-ne-palette  has a .ps-ne-rail-grabber on its right edge
 *   .ps-ne-settings has a .ps-ne-rail-grabber on its left edge
 *
 * The resize is driven by Alpine state; widths persist in localStorage
 * so the layout survives a reload.
 */
class RailResizeTest extends DuskTestCase
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
            localStorage.removeItem('psPbLeftRailW');
            localStorage.removeItem('psPbRightRailW');
            localStorage.removeItem('psPbNeLeftRailW');
            localStorage.removeItem('psPbNeRightRailW');
        JS);
        $b->pause(300);
    }

    public function test_block_left_rail_has_a_grabber_on_its_right_edge(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $exists = $b->script(<<<'JS'
                return !! document.querySelector('.ps-pb-rail--left .ps-pb-rail-grabber');
            JS)[0];

            $this->assertTrue((bool) $exists, '.ps-pb-rail--left should have a .ps-pb-rail-grabber');
        });
    }

    public function test_block_right_rail_has_a_grabber_on_its_left_edge(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $exists = $b->script(<<<'JS'
                return !! document.querySelector('.ps-pb-rail--right .ps-pb-rail-grabber');
            JS)[0];

            $this->assertTrue((bool) $exists, '.ps-pb-rail--right should have a .ps-pb-rail-grabber');
        });
    }

    public function test_dragging_left_rail_grabber_grows_the_rail(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $info = $b->script(<<<'JS'
                const rail = document.querySelector('.ps-pb-rail--left');
                const before = rail.getBoundingClientRect().width;
                // Simulate a drag: open the Alpine scope and bump the state
                // directly · the production grabber is wired to a Alpine
                // pointerdown handler, this exercises the resulting state.
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                const scope = Alpine.$data(root);
                scope.leftRailW = (scope.leftRailW || 144) + 80;
                return { before };
            JS)[0];
            $b->pause(400);

            $after = $b->script(<<<'JS'
                return document.querySelector('.ps-pb-rail--left').getBoundingClientRect().width;
            JS)[0];

            $this->assertGreaterThan($info['before'] + 60, $after,
                'Left rail should grow by ~80px after bumping leftRailW · before='.$info['before'].' after='.$after);
        });
    }

    public function test_node_editor_palette_has_a_grabber(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('toggleDrawer');
            JS);
            $b->waitFor('.ps-ne-drawer', 5);
            $b->pause(400);

            $exists = $b->script(<<<'JS'
                return !! document.querySelector('.ps-ne-palette .ps-ne-rail-grabber');
            JS)[0];

            $this->assertTrue((bool) $exists, '.ps-ne-palette should have a .ps-ne-rail-grabber');
        });
    }

    public function test_node_editor_settings_has_a_grabber_when_a_node_is_selected(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('toggleDrawer');
            JS);
            $b->waitFor('.ps-ne-drawer', 5);
            $b->pause(400);
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                wire.call('addNode', 'source.constant');
            JS);
            $b->pause(800);

            $exists = $b->script(<<<'JS'
                return !! document.querySelector('.ps-ne-settings .ps-ne-rail-grabber');
            JS)[0];

            $this->assertTrue((bool) $exists, '.ps-ne-settings should have a .ps-ne-rail-grabber when a node is selected');
        });
    }

    public function test_visual_resized_rails(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            // Open the drawer + add a node so the right rail renders.
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                wire.call('toggleDrawer');
            JS);
            $b->waitFor('.ps-ne-drawer', 5);
            $b->pause(400);
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                wire.call('addNode', 'source.constant');
                wire.call('selectBlock', '0');
            JS);
            $b->pause(800);
            // Make the left block rail wider, the right narrower, and the
            // node rails both wider to show the grabbers in action.
            $b->script(<<<'JS'
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                const s = Alpine.$data(root);
                s.leftRailW    = 220;
                s.rightRailW   = 320;
                s.neLeftRailW  = 220;
                s.neRightRailW = 280;
            JS);
            $b->pause(500);
            $b->screenshot('rails-resized');
        });
    }

    public function test_rail_widths_persist_to_localstorage(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $b->script(<<<'JS'
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                Alpine.$data(root).leftRailW = 240;
            JS);
            $b->pause(400);

            $stored = $b->script(<<<'JS'
                return localStorage.getItem('psPbLeftRailW');
            JS)[0];

            $this->assertSame('240', $stored,
                'leftRailW should persist to localStorage · got '.var_export($stored, true));
        });
    }
}
