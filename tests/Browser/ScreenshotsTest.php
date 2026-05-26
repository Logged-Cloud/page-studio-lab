<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Captures the marketing screenshots referenced by the package README.
 *
 * Each test produces a single PNG at tests/Browser/screenshots/<name>.png.
 * A separate shell step copies them into the package's docs/screenshots/
 * directory after the suite passes.
 */
class ScreenshotsTest extends DuskTestCase
{
    protected function lwCall(Browser $b, string $method, ...$args): void
    {
        $jsonArgs = json_encode($args);
        $b->script(
            "Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).call('$method', ...$jsonArgs);"
        );
    }

    protected function lwSet(Browser $b, string $prop, mixed $value): void
    {
        $json = json_encode($value);
        $b->script(
            "Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('$prop', $json);"
        );
    }

    protected function readProp(Browser $b, string $prop): mixed
    {
        return $b->script(
            "return Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).get('$prop');"
        )[0];
    }

    /**
     * Open the editor + wipe nodes/edges so we paint a clean graph.
     */
    protected function freshEditor(Browser $b): void
    {
        $b->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]')
            ->waitFor('[data-component="page-studio.node-editor"]')
            ->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('nodes', []);");
        $b->pause(300);
        $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('edges', []);");
        $b->pause(300);
    }

    /**
     * Replace the page's block tree with a polished, demo-worthy fixture.
     * One section containing heading + paragraph + a 2-columns layout, with
     * a card in each column. Selects the heading so the right-hand settings
     * panel renders.
     */
    protected function seedRichBlocks(Browser $b): void
    {
        $blocks = [[
            'id' => 'sec_1',
            'type' => 'section',
            'settings' => ['padding' => 'lg', 'background' => 'tint'],
            'children' => [
                'body' => [
                    [
                        'id' => 'hd_1',
                        'type' => 'heading',
                        'settings' => ['text' => 'Build pages from a form', 'level' => 'h1', 'align' => 'left'],
                    ],
                    [
                        'id' => 'pg_1',
                        'type' => 'paragraph',
                        'settings' => ['text' => 'Drag blocks onto the canvas. Click any block to edit its settings on the right. Drop {{ variableName }} chips into any text field to bind values from the route or node graph.'],
                    ],
                    [
                        'id' => 'col_1',
                        'type' => '2-columns',
                        'settings' => ['gap' => 'md'],
                        'children' => [
                            'left' => [
                                [
                                    'id' => 'card_l',
                                    'type' => 'card',
                                    'settings' => ['title' => 'Route builder', 'subtitle' => 'URL -> typed variables', 'tone' => 'info'],
                                    'children' => [
                                        'body' => [
                                            [
                                                'id' => 'pg_l',
                                                'type' => 'paragraph',
                                                'settings' => ['text' => 'Type a URL, turn segments into typed, reusable variables.'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'right' => [
                                [
                                    'id' => 'card_r',
                                    'type' => 'card',
                                    'settings' => ['title' => 'Node editor', 'subtitle' => 'Blender-style graph', 'tone' => 'success'],
                                    'children' => [
                                        'body' => [
                                            [
                                                'id' => 'pg_r',
                                                'type' => 'paragraph',
                                                'settings' => ['text' => 'Compose new variables from route values, models, transforms, math, and image filters.'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]];

        $this->lwSet($b, 'blocks', $blocks);
        $b->pause(500);
    }

    /** @test */
    public function test_shot_01_route_builder(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/routes')
                ->pause(1500);
            $this->assertTrue(true);
            $b->screenshot('route-builder');
        });
    }

    /** @test */
    public function test_shot_02_variable_library(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/variables')
                ->pause(1500);
            $this->assertTrue(true);
            $b->screenshot('variable-library');
        });
    }

    /** @test */
    public function test_shot_03_page_builder(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]')
                ->pause(800);

            // Seed a rich block tree so the canvas is not the bare dusk.test paragraph.
            $this->seedRichBlocks($b);

            // Close the bottom drawer so the page-builder view dominates.
            $drawerOpen = $b->script("return !!document.querySelector('[data-component=\"page-studio.node-editor\"]');")[0];
            if ($drawerOpen) {
                $b->click('button[wire\\:click="toggleDrawer"]')
                    ->waitUntilMissing('[data-component="page-studio.node-editor"]')
                    ->pause(400);
            }

            // Select the top heading so the right-side settings panel appears.
            $this->lwCall($b, 'selectBlock', '0/body/0');
            $b->pause(700)
                ->screenshot('page-builder');
        });
    }

    /** @test */
    public function test_shot_04_node_editor(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900);
            $this->freshEditor($b);

            // Make sure the drawer is open.
            $drawerOpen = $b->script("return !!document.querySelector('[data-component=\"page-studio.node-editor\"]');")[0];
            if (! $drawerOpen) {
                $b->click('button[wire\\:click="toggleDrawer"]')
                    ->waitFor('[data-component="page-studio.node-editor"]')
                    ->pause(400);
            }

            // Top chain · route_variable(userId) -> uppercase -> concat -> output(displayName).
            $this->lwCall($b, 'addNode', 'source.route_variable', 60, 60);
            $b->pause(250);
            $this->lwCall($b, 'addNode', 'transform.uppercase', 300, 60);
            $b->pause(250);
            $this->lwCall($b, 'addNode', 'transform.concat', 540, 60);
            $b->pause(250);
            $this->lwCall($b, 'addNode', 'output', 800, 60);
            $b->pause(250);

            // Bottom chain · model_finder -> field -> output(name).
            $this->lwCall($b, 'addNode', 'source.model_finder', 60, 300);
            $b->pause(250);
            $this->lwCall($b, 'addNode', 'transform.field', 300, 300);
            $b->pause(250);
            $this->lwCall($b, 'addNode', 'output', 540, 300);
            $b->pause(350);

            $nodes = $this->readProp($b, 'nodes');
            $routeVarId   = $nodes[0]['id'];
            $upperId      = $nodes[1]['id'];
            $concatId     = $nodes[2]['id'];
            $outDispId    = $nodes[3]['id'];
            $modelFindId  = $nodes[4]['id'];
            $fieldId      = $nodes[5]['id'];
            $outNameId    = $nodes[6]['id'];

            // Configure settings.
            $this->lwSet($b, 'nodes.0.settings.variable_name', 'userId');
            $b->pause(150);
            $this->lwSet($b, 'nodes.2.settings.separator', ' ');
            $b->pause(150);
            $this->lwSet($b, 'nodes.3.settings.name', 'displayName');
            $b->pause(150);
            $this->lwSet($b, 'nodes.5.settings.field', 'name');
            $b->pause(150);
            $this->lwSet($b, 'nodes.6.settings.name', 'name');
            $b->pause(250);

            // Wire top chain.
            $this->lwCall($b, 'startConnection', $routeVarId, 'value');
            $b->pause(150);
            $this->lwCall($b, 'completeConnection', $upperId, 'text');
            $b->pause(200);
            $this->lwCall($b, 'startConnection', $upperId, 'value');
            $b->pause(150);
            $this->lwCall($b, 'completeConnection', $concatId, 'a');
            $b->pause(200);
            $this->lwCall($b, 'startConnection', $concatId, 'value');
            $b->pause(150);
            $this->lwCall($b, 'completeConnection', $outDispId, 'value');
            $b->pause(250);

            // Wire bottom chain.
            $this->lwCall($b, 'startConnection', $modelFindId, 'model');
            $b->pause(150);
            $this->lwCall($b, 'completeConnection', $fieldId, 'source');
            $b->pause(200);
            $this->lwCall($b, 'startConnection', $fieldId, 'value');
            $b->pause(150);
            $this->lwCall($b, 'completeConnection', $outNameId, 'value');
            $b->pause(350);

            // Tidy + give the canvas a beat to paint wires.
            $this->lwCall($b, 'tidy');
            $b->pause(700);

            // Select a middle node (the concat) so the right-side settings panel renders.
            $this->lwCall($b, 'selectNode', $concatId);
            $b->pause(400);
            $hasSelected = (int) $b->script("return document.querySelectorAll('.ps-ne-node.is-selected, .ps-ne-node[data-selected=\"true\"]').length;")[0];
            if ($hasSelected === 0) {
                $b->script("const els = document.querySelectorAll('.ps-ne-node'); if (els[2]) els[2].click();");
                $b->pause(500);
            }

            $b->pause(600)
                ->screenshot('node-editor');
        });
    }

    /** @test */
    public function test_shot_05_node_palette(): void
    {
        $this->browse(function (Browser $b) {
            // Narrower window keeps the palette rail prominent in the crop.
            $b->resize(1100, 900);
            $this->freshEditor($b);

            $drawerOpen = $b->script("return !!document.querySelector('[data-component=\"page-studio.node-editor\"]');")[0];
            if (! $drawerOpen) {
                $b->click('button[wire\\:click="toggleDrawer"]')
                    ->waitFor('[data-component="page-studio.node-editor"]')
                    ->pause(400);
            }

            // Scroll the palette into view if it's tucked away.
            $b->script("document.querySelector('.ps-ne-palette')?.scrollIntoView({block: 'center'});");
            $b->pause(600)
                ->screenshot('node-palette');
        });
    }

    /** @test */
    public function test_shot_06_finder(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]')
                ->pause(600);

            // Seed a rich block tree so finder results have texture.
            $this->seedRichBlocks($b);

            // Also seed a few nodes so finder shows mixed kinds.
            $drawerOpen = $b->script("return !!document.querySelector('[data-component=\"page-studio.node-editor\"]');")[0];
            if (! $drawerOpen) {
                $b->click('button[wire\\:click="toggleDrawer"]')
                    ->waitFor('[data-component="page-studio.node-editor"]')
                    ->pause(400);
            }
            $this->lwSet($b, 'nodes', []);
            $this->lwSet($b, 'edges', []);
            $b->pause(200);
            $this->lwCall($b, 'addNode', 'source.route_variable', 80, 80);
            $b->pause(200);
            $this->lwCall($b, 'addNode', 'transform.uppercase', 320, 80);
            $b->pause(200);
            $this->lwSet($b, 'nodes.0.settings.variable_name', 'header_userId');
            $b->pause(300);

            // Open the finder via `/` key.
            $b->script("document.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true }));");
            $b->pause(400);

            // Focus the finder input and type the query.
            $b->script("document.querySelector('.ps-pb-find-input')?.focus();");
            $b->pause(150);
            $b->type('.ps-pb-find-input', 'head')
                ->pause(600);

            // Hover the first row so the highlighted state shows.
            $b->script("const r = document.querySelector('.ps-pb-find-row'); if (r) { r.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true })); }");
            $b->pause(300);

            $b->screenshot('finder');
        });
    }
}
