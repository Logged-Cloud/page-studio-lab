<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * One device toggle to rule them all · the topbar's "Phone / Tablet /
 * Desktop" buttons drive BOTH the edit-mode canvas-wrap frame and the
 * preview-mode preview-pane frame. Previously the preview pane had its
 * own buttons inside it on a separate Alpine scope; the topbar buttons
 * (icon-only) didn't affect preview rendering. This test locks the
 * unified behaviour.
 */
class UnifiedDeviceToggleTest extends DuskTestCase
{
    protected function fresh(Browser $b): void
    {
        $b->resize(1440, 900)
            ->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->pause(400);
        $b->script(<<<'JS'
            const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
            if (wire.get('previewMode')) wire.call('togglePreview');
            wire.set('blocks', [{
                id: 'b1', type: 'paragraph', settings: { text: 'hello' },
            }]);
        JS);
        $b->pause(600);
    }

    public function test_topbar_has_labelled_device_buttons(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            $labels = $b->script(<<<'JS'
                const btns = Array.from(document.querySelectorAll('.ps-pb-device-toggle .ps-pb-device-btn'));
                return btns.map(el => el.textContent.replace(/\s+/g, ' ').trim());
            JS)[0];

            $this->assertNotEmpty($labels, 'topbar device buttons should exist');
            $this->assertStringContainsString('Phone',   implode(' ', $labels ?? []),
                'a Phone button should exist · got '.json_encode($labels));
            $this->assertStringContainsString('Tablet',  implode(' ', $labels ?? []),
                'a Tablet button should exist · got '.json_encode($labels));
            $this->assertStringContainsString('Desktop', implode(' ', $labels ?? []),
                'a Desktop button should exist · got '.json_encode($labels));
        });
    }

    public function test_topbar_phone_button_drives_preview_pane(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            // Enter preview mode.
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('togglePreview');
            JS);
            $b->waitFor('.ps-pb-preview-pane', 5);
            $b->pause(400);

            // Click the topbar "Phone" button.
            $b->script(<<<'JS'
                const btn = Array.from(document.querySelectorAll('.ps-pb-device-toggle .ps-pb-device-btn'))
                    .find(el => el.textContent.includes('Phone'));
                if (btn) btn.click();
            JS);
            $b->pause(500);

            $hasPhoneClass = $b->script(<<<'JS'
                const pane = document.querySelector('.ps-pb-preview-pane');
                return pane ? pane.classList.contains('ps-pb-preview-pane--phone') : false;
            JS)[0];

            $this->assertTrue((bool) $hasPhoneClass,
                'Topbar Phone button should add .ps-pb-preview-pane--phone to the preview pane');
        });
    }

    public function test_inline_preview_pane_buttons_are_gone(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('togglePreview');
            JS);
            $b->waitFor('.ps-pb-preview-pane', 5);
            $b->pause(400);

            $count = $b->script(<<<'JS'
                return document.querySelectorAll('.ps-pb-preview-toolbar').length;
            JS)[0];

            $this->assertSame(0, (int) $count,
                'The in-preview .ps-pb-preview-toolbar should no longer render · device buttons live in the topbar now');
        });
    }

    public function test_visual_topbar_device_buttons_in_preview(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('togglePreview');
            JS);
            $b->waitFor('.ps-pb-preview-pane', 5);
            $b->pause(400);
            $b->script(<<<'JS'
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                Alpine.$data(root).device = 'phone';
            JS);
            $b->pause(400);
            $b->screenshot('topbar-device-buttons-phone-preview');
        });
    }

    public function test_device_setting_persists_across_preview_toggle(): void
    {
        $this->browse(function (Browser $b) {
            $this->fresh($b);

            // Set tablet in edit mode.
            $b->script(<<<'JS'
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                Alpine.$data(root).device = 'tablet';
            JS);
            $b->pause(300);

            // Enter preview · expect the pane to come up as tablet.
            $b->script(<<<'JS'
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('togglePreview');
            JS);
            $b->waitFor('.ps-pb-preview-pane--tablet', 5);

            $hasTablet = $b->script("return !! document.querySelector('.ps-pb-preview-pane--tablet');")[0];
            $this->assertTrue((bool) $hasTablet,
                'Preview pane should pick up the topbar device selection');
        });
    }
}
