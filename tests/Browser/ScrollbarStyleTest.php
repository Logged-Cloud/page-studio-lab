<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Visual smoke test for the new subtle scrollbar styles · captures
 * a wide-viewport screenshot of /showcase/procedural with the
 * drawer open + the variables strip + several scrollable panels
 * in shot, so we can eyeball the chrome.
 */
class ScrollbarStyleTest extends DuskTestCase
{
    public function test_capture_scrollbar_style_at_desktop_width(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1280, 800)
                ->visit('http://studio-logged-nginx/playground?page=88')
                ->waitFor('[data-component="page-studio.page-builder"]', 8);
            $b->pause(1200);

            // Topbar should wrap, not force horizontal scroll · assert
            // the document isn't wider than the viewport.
            $overflow = $b->script(<<<'JS'
                return {
                    docW: document.documentElement.scrollWidth,
                    vpW: window.innerWidth,
                };
            JS)[0];
            $this->assertLessThanOrEqual((int) $overflow['vpW'] + 4, (int) $overflow['docW'],
                'document width should not exceed viewport · got '.json_encode($overflow));

            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(800);
            $b->screenshot('scrollbar-style-desktop');

            // Also at phone width since the user reported on mobile too.
            $b->resize(390, 844);
            $b->pause(400);
            $b->screenshot('scrollbar-style-phone');
        });
    }
}
