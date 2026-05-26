<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Device-frame preview · selecting Phone or Tablet in the topbar
 * device toggle clamps the canvas width, but until now the columns
 * blocks inside the canvas continued to render at desktop widths.
 * A 2-up or 3-up grid inside a 390px phone frame crushed content
 * into unreadable slivers · the user pointed this out as
 * "mobile preview looks wrong".
 *
 * The columns blocks emit a media query keyed on viewport width
 * (<= 640px), which doesn't fire when the viewport is 1440px and
 * only the canvas-wrap is phone-sized. The fix uses parent-class
 * selectors that match regardless of viewport.
 */
class DeviceFramePreviewTest extends DuskTestCase
{
    protected function fresh(Browser $b): void
    {
        $b->resize(1440, 900)
            ->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->pause(400);

        // Seed a 3-column block so we have something to verify. Every
        // block needs an `id` field because the editor's block template
        // wire:key's on it · skipping the id leaves the page-builder
        // unable to render the recursive editor view.
        $b->script(<<<'JS'
            const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
            wire.set('blocks', [
                {
                    id: 'b-cols3', type: 'columns-3', settings: {},
                    children: {
                        left:   [{ id: 'b-l', type: 'paragraph', settings: { text: 'LEFT-COL' } }],
                        middle: [{ id: 'b-m', type: 'paragraph', settings: { text: 'MIDDLE-COL' } }],
                        right:  [{ id: 'b-r', type: 'paragraph', settings: { text: 'RIGHT-COL' } }],
                    },
                },
            ]);
        JS);
        $b->pause(700);
    }

    protected function setDevice(Browser $b, string $device): void
    {
        // Device frame applies in preview mode · enter it first.
        $b->script(<<<'JS'
            const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
            if (! wire.get('previewMode')) wire.call('togglePreview');
        JS);
        $b->waitFor('.ps-pb-preview-pane', 5);
        $b->pause(400);
        $b->script("
            const wrap = document.querySelector('.ps-pb-preview-wrap');
            if (wrap) Alpine.\$data(wrap).device = '{$device}';
        ");
        $b->pause(500);
    }

    public function test_columns_3_collapse_to_one_column_in_phone_preview(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->setDevice($b, 'phone');

            $cols = $b->script(<<<'JS'
                const grid = document.querySelector('.ps-pb-preview-pane--phone .ps-render-cols-3');
                if (! grid) return null;
                return window.getComputedStyle(grid).gridTemplateColumns;
            JS)[0];

            $this->assertNotNull($cols, '.ps-render-cols-3 should exist inside the phone preview pane');
            $this->assertStringNotContainsString(' ', $cols,
                'columns-3 should collapse to one track in phone preview · got '.$cols);
        });
    }

    public function test_columns_3_become_two_columns_in_tablet_preview(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->setDevice($b, 'tablet');

            $cols = $b->script(<<<'JS'
                const grid = document.querySelector('.ps-pb-preview-pane--tablet .ps-render-cols-3');
                if (! grid) return null;
                return window.getComputedStyle(grid).gridTemplateColumns;
            JS)[0];

            $this->assertNotNull($cols, 'columns-3 should exist in tablet preview');
            $tracks = preg_split('/\s+/', trim($cols));
            $this->assertCount(2, $tracks ?? [],
                'columns-3 should render as 2-up in tablet preview · got '.$cols);
        });
    }

    public function test_columns_3_stays_three_in_desktop_preview(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->setDevice($b, 'desktop');

            $cols = $b->script(<<<'JS'
                const grid = document.querySelector('.ps-pb-preview-pane--desktop .ps-render-cols-3');
                if (! grid) return null;
                return window.getComputedStyle(grid).gridTemplateColumns;
            JS)[0];

            $tracks = preg_split('/\s+/', trim((string) $cols));
            $this->assertCount(3, $tracks ?? [],
                'columns-3 should render as 3-up in desktop preview · got '.var_export($cols, true));
        });
    }

    public function test_visual_phone_preview_collapses(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $this->setDevice($b, 'phone');
            $b->screenshot('device-phone-collapsed');
        });
    }
}
