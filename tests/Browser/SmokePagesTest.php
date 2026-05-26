<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Smoke checks · every Livewire-mounted page in the lab loads cleanly,
 * no 500s, no stray parse errors leaking through. Catches regressions
 * like the heredoc `\$this` typos that broke /variables and
 * /route-builder in past sessions.
 */
class SmokePagesTest extends DuskTestCase
{
    public function test_root_landing_loads(): void
    {
        $this->browse(function (Browser $b) {
            $b->visit('/')
                ->pause(800)
                ->assertDontSee('Internal Server Error')
                ->assertDontSee('ParseError')
                ->assertDontSee('syntax error');
        });
    }

    public function test_route_builder_loads_without_5xx(): void
    {
        $this->browse(function (Browser $b) {
            $b->visit('/route-builder')
                ->pause(800)
                ->assertDontSee('Internal Server Error')
                ->assertDontSee('ParseError')
                ->assertDontSee('syntax error')
                ->assertSee('Route builder');
        });
    }

    public function test_variable_library_loads_without_5xx(): void
    {
        $this->browse(function (Browser $b) {
            $b->visit('/variables')
                ->pause(800)
                ->assertDontSee('Internal Server Error')
                ->assertDontSee('ParseError')
                ->assertSee('Variable library');
        });
    }

    public function test_saved_routes_loads_without_5xx(): void
    {
        $this->browse(function (Browser $b) {
            $b->visit('/routes')
                ->pause(800)
                ->assertDontSee('Internal Server Error')
                ->assertSee('Saved routes');
        });
    }
}
