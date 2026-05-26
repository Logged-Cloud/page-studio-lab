<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end browser checks for the page-studio builder running against the
 * live https://page-studio.logged.cloud lab. Read-only · seeded route id 3
 * (dusk.test) has a single {userId} variable to insert.
 */
class PageBuilderTest extends DuskTestCase
{
    /** @test */
    public function test_builder_loads_with_palette_and_canvas(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]')
                // Section headings are upper-cased via CSS · Selenium reads
                // the CSS-rendered string, so assert UPPERCASE.
                ->assertSee('CONTENT')
                ->assertSee('LAYOUT')
                ->assertSee('VARIABLES')
                ->assertSeeIn('code.ps-pb-path', '/dusk/{userId}');
        });
    }

    /** @test */
    public function test_node_drawer_toggles_open_with_the_nodes_button(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]')
                // Drawer is open by default so the node editor is visible.
                ->assertPresent('[data-component="page-studio.node-editor"]')
                // Clicking the Nodes button collapses the drawer.
                ->click('button[wire\\:click="toggleDrawer"]')
                ->waitUntilMissing('[data-component="page-studio.node-editor"]')
                // Clicking again brings it back.
                ->click('button[wire\\:click="toggleDrawer"]')
                ->waitFor('[data-component="page-studio.node-editor"]');
        });
    }

    /** @test */
    public function test_clicking_a_palette_block_adds_it_to_the_canvas(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]')
                // First wipe any existing blocks via the artisan-seeded clean state.
                ->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('blocks', [])");
            $browser->pause(400);
            $browser->click('.ps-pb-palette-item')   // first palette item = Heading
                ->waitFor('.ps-pb-block-wrap')
                ->assertPresent('.ps-pb-block-wrap');
        });
    }

    /** @test */
    public function test_inserting_a_variable_via_the_var_button_updates_the_textarea(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]')
                ->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('blocks', [])");
            $browser->pause(400);

            // Add a paragraph block · the second palette item under Content.
            $browser->click('.ps-pb-palette-item:nth-of-type(2)')
                ->waitFor('.ps-pb-block-wrap')
                ->click('.ps-pb-block-wrap')   // select it so settings panel appears
                ->waitFor('textarea[data-wire-prop]');

            // Snapshot what the textarea looks like, then click the var button
            // and pick the only variable in the picker.
            $beforeValue = $browser->value('textarea[data-wire-prop]');

            $browser->click('.ps-pb-var-btn')
                ->waitFor('.ps-pb-var-picker')
                ->click('.ps-pb-var-picker-item');

            $browser->waitForTextIn('.ps-pb-toast', 'Inserted');

            // Pull the textarea value again from the DOM after Livewire writes back.
            $browser->pause(400);
            $afterValue = $browser->value('textarea[data-wire-prop]');

            $this->assertNotSame($beforeValue, $afterValue,
                "Textarea value should change after var insertion · before='$beforeValue'");
            $this->assertStringContainsString('{{ userId }}', $afterValue,
                "Inserted token should be in the textarea · got='$afterValue'");
        });
    }
    /** @test */
    public function test_clicking_a_palette_node_adds_it_to_the_canvas(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]')
                ->waitFor('[data-component="page-studio.node-editor"]')
                // Reset graph state via Livewire so we don't depend on prior runs.
                ->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes', []);");
            $browser->pause(400)
                ->click('.ps-ne-palette-item')   // first palette item = Source · Route variable
                ->waitFor('.ps-ne-node', 8)
                ->assertPresent('.ps-ne-node');
        });
    }
    /** @test */
    public function test_dropping_a_variable_chip_onto_the_canvas_creates_a_source_node(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]')
                ->waitFor('[data-component="page-studio.node-editor"]')
                ->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes', []);");
            $browser->pause(400);
            // HTML5 drag is hard to simulate in WebDriver · short-circuit by
            // calling the Livewire method that the chip drop would trigger,
            // then assert the resulting node carries the right variable_name.
            $browser->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).call('addNodeForVariable', 'userId', 200, 50);");
            $browser->waitFor('.ps-ne-node', 6)->pause(200);

            $varName = $browser->script("const id = document.querySelector('[wire\\\\:id]').getAttribute('wire:id'); const n = (Livewire.find(id).get('nodes')||[])[0]; return n ? n.settings.variable_name : null;")[0];
            $this->assertSame('userId', $varName, 'New node should be pre-set to the dragged variable name');
        });
    }
    /** @test */
    public function test_right_clicking_a_variable_chip_adds_a_source_node(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]')
                ->waitFor('[data-component="page-studio.node-editor"]')
                ->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes', []);");
            $browser->pause(400);

            // WebDriver's right-click is awkward · trigger contextmenu directly
            // on the variable chip so the Alpine handler fires the same way as
            // a user right-click would.
            $browser->script("document.querySelector('.ps-pb-var-chip').dispatchEvent(new MouseEvent('contextmenu', {bubbles: true, cancelable: true}));");
            $browser->waitFor('.ps-ne-node', 6)->pause(200);

            $varName = $browser->script("const id = document.querySelector('[wire\\\\:id]').getAttribute('wire:id'); const n = (Livewire.find(id).get('nodes')||[])[0]; return n ? n.settings.variable_name : null;")[0];
            $this->assertSame('userId', $varName, 'Right-click should spawn a Route-variable source node pre-filled with the chip\'s name');
        });
    }
    /** @test */
    public function test_right_click_on_canvas_opens_a_node_picker_menu(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]')
                ->waitFor('[data-component="page-studio.node-editor"]')
                ->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes', []);");
            $browser->pause(300);

            // Fire a contextmenu on the canvas itself · Selenium can't right-
            // click cleanly across all backends so we synthesize the event.
            $browser->script("const c = document.querySelector('.ps-ne-canvas-wrap'); c.dispatchEvent(new MouseEvent('contextmenu', {bubbles: true, cancelable: true, clientX: 250, clientY: 350}));");
            $browser->waitFor('.ps-ne-ctx-menu', 5);
            // text-transform: uppercase in CSS · assertSee compares pre-CSS
            // text content. Switch to a JS-extracted list which is robust.
            $items = $browser->script("return Array.from(document.querySelectorAll('.ps-ne-ctx-item')).map(b => b.textContent.replace(/\\s+/g,' ').trim());")[0];
            $hasRouteVar = false;
            foreach ($items as $t) {
                if (str_contains($t, 'Route variable')) { $hasRouteVar = true; break; }
            }
            $this->assertTrue($hasRouteVar, 'Menu should include the Route variable node type');

            // Click the Route variable item · should drop a node.
            $browser->script("Array.from(document.querySelectorAll('.ps-ne-ctx-item')).find(b => b.textContent.includes('Route variable')).click();");
            $browser->waitFor('.ps-ne-node', 6)->pause(200);

            $count = (int) $browser->script("return document.querySelectorAll('.ps-ne-node').length;")[0];
            $this->assertSame(1, $count, 'Right-click menu should spawn exactly one node');
        });
    }

}
