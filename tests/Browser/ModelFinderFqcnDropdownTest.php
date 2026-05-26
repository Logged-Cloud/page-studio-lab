<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The Model finder node's `MODEL FQCN` setting should render as a
 * <select> sourced from the host app's #[ExposeToModelFinder]-
 * decorated models. End-to-end · proves the attribute + discovery +
 * service-provider promotion + settings-panel rendering all line up.
 *
 * Reproduces the user's report on page-studio.logged.cloud: the
 * field showed as a free-text input even though a Model finder node
 * was selected. The chain was broken at the deployment-side: no
 * attributed model + no discovery cache + the un-released attribute
 * code path.
 */
class ModelFinderFqcnDropdownTest extends DuskTestCase
{
    public function test_model_fqcn_setting_renders_as_a_select_populated_from_discovered_models(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(500);

            // Seed a Model finder node in the graph and select it so
            // the node-settings panel renders with the FQCN field.
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
                wire.set('nodes', [{
                    id:       'mf-1',
                    type:     'source.model_finder',
                    settings: { model_class: 'App\\Models\\User', finder_key: 'id', expose_fields: false },
                    position: { x: 120, y: 120 },
                }]);
                wire.set('selectedNodeId', 'mf-1');
            JS);
            $b->pause(900);

            // The FQCN row · find by its label text and assert the
            // input element is a <select> with at least one option
            // matching an attributed model.
            $shape = $b->script(<<<'JS'
                const fields = document.querySelectorAll('.ps-ne-settings .ps-pb-field');
                for (const f of fields) {
                    const label = f.querySelector('label');
                    if (label && /Model FQCN/i.test(label.textContent || '')) {
                        const sel = f.querySelector('select');
                        const inp = f.querySelector('input[type="text"]');
                        return {
                            isSelect: !! sel,
                            isText:   !! inp,
                            options:  sel ? Array.from(sel.options).map(o => o.value) : [],
                        };
                    }
                }
                return null;
            JS)[0];

            $this->assertNotNull($shape, 'MODEL FQCN field should be present in the node-settings panel');
            $this->assertTrue((bool) ($shape['isSelect'] ?? false),
                'MODEL FQCN must render as a <select> · got '.json_encode($shape));
            $this->assertFalse((bool) ($shape['isText'] ?? false),
                'MODEL FQCN must NOT render as a free-text <input> · got '.json_encode($shape));
            $this->assertContains('App\\Models\\User', $shape['options'] ?? [],
                'App\\Models\\User should be in the dropdown · App\\Models\\User must carry #[ExposeToModelFinder] and the discovery cache must have been rebuilt');
        });
    }

    public function test_find_by_column_renders_as_a_select_sourced_from_the_models_declared_findBy(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(500);

            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (! wire.get('drawerOpen')) wire.call('toggleDrawer');
                wire.set('nodes', [{
                    id:       'mf-1',
                    type:     'source.model_finder',
                    settings: { model_class: 'App\\Models\\User', finder_key: 'id', expose_fields: false },
                    position: { x: 120, y: 120 },
                }]);
                wire.set('selectedNodeId', 'mf-1');
            JS);
            $b->pause(900);

            $shape = $b->script(<<<'JS'
                const fields = document.querySelectorAll('.ps-ne-settings .ps-pb-field');
                for (const f of fields) {
                    const label = f.querySelector('label');
                    if (label && /Find by column/i.test(label.textContent || '')) {
                        const sel = f.querySelector('select');
                        return {
                            isSelect: !! sel,
                            options:  sel ? Array.from(sel.options).map(o => o.value) : [],
                        };
                    }
                }
                return null;
            JS)[0];

            $this->assertNotNull($shape, 'Find by column field should be present in the node-settings panel');
            $this->assertTrue((bool) ($shape['isSelect'] ?? false),
                'Find by column must render as a <select> when the selected model declares findBy · got '.json_encode($shape));
            $this->assertContains('id', $shape['options'] ?? [],
                'id should be in the Find by column options (the default for any attributed model) · got '.json_encode($shape));
        });
    }
}
