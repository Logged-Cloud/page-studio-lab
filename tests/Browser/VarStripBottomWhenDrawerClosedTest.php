<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Regression · the variables strip's `bottom` was computed as
 * `calc(var(--ps-pb-drawer-h, 0) + 8px)`. When the JS-set CSS
 * variable was stale (e.g. left at a previous drawer height), the
 * strip ended up floating mid-screen instead of pinned to the
 * viewport floor. Anchor to a literal `bottom: 8px` whenever the
 * drawer is closed.
 */
class VarStripBottomWhenDrawerClosedTest extends DuskTestCase
{
    public function test_var_strip_sits_at_the_viewport_floor_when_drawer_is_closed(): void
    {
        $route = \LoggedCloud\PageStudio\Models\RouteDefinition::firstOrCreate(
            ['name' => 'dusk.var-strip-floor'],
            ['method' => 'GET', 'path_template' => '/dusk-var-strip-floor/{slug}'],
        );
        // Make sure at least one variable exists · the strip only
        // renders when this->variables is non-empty.
        $slug = \LoggedCloud\PageStudio\Models\Variable::firstOrCreate(
            ['name' => 'slug'],
            ['type' => 'slug', 'examples' => ['getting-started']],
        );
        if ($route->segments()->count() === 0) {
            \LoggedCloud\PageStudio\Models\RouteSegment::create([
                'route_id' => $route->id, 'position' => 0, 'kind' => 'literal',  'literal_value' => 'dusk-var-strip-floor',
            ]);
            \LoggedCloud\PageStudio\Models\RouteSegment::create([
                'route_id' => $route->id, 'position' => 1, 'kind' => 'variable', 'variable_id'   => $slug->id,
            ]);
        }
        \LoggedCloud\PageStudio\Models\Page::firstOrCreate(
            ['route_id' => $route->id],
            ['blocks' => [], 'status' => 'draft'],
        );

        $this->browse(function (Browser $b) use ($route) {
            $b->resize(1440, 900)
                ->visit('/pages/'.$route->id.'/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(700);

            // Poison the drawer-h CSS variable to mimic a stale state
            // left over from a prior open/close cycle.
            $b->script(<<<'JS'
                document.documentElement.style.setProperty('--ps-pb-drawer-h', '480px');
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                if (wire.get('drawerOpen')) wire.call('toggleDrawer');
            JS);
            $b->pause(500);

            $diag = $b->script(<<<'JS'
                const el = document.querySelector('.ps-pb-var-strip');
                if (! el) return null;
                const r = el.getBoundingClientRect();
                return {
                    bottom: Math.round(window.innerHeight - r.bottom),
                    drawerOpen: Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('drawerOpen'),
                    drawerHCss: getComputedStyle(document.documentElement).getPropertyValue('--ps-pb-drawer-h'),
                };
            JS)[0];

            $this->assertNotNull($diag, '.ps-pb-var-strip must be in the DOM');
            $this->assertFalse((bool) $diag['drawerOpen'], 'drawer must be closed for this case');
            $this->assertLessThan(12, abs((int) $diag['bottom']),
                'with drawer closed, var-strip should sit ~8px from the viewport floor regardless of stale --ps-pb-drawer-h · got '.json_encode($diag));
        });

        \LoggedCloud\PageStudio\Models\NodeGraph::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\Page::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteSegment::where('route_id', $route->id)->delete();
        \LoggedCloud\PageStudio\Models\RouteDefinition::where('id', $route->id)->delete();
    }
}
