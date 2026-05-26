<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LoggedCloud\PageStudio\Models\NodeGraph;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Models\Variable;
use LoggedCloud\PageStudio\Templates\TemplateRegistry;

/**
 * Walks every registered Template and installs it via the same logic as
 * `page-studio:install-template`, plus seeds a handful of free-standing
 * demo variables so the variable library has texture out of the box.
 * Skips rows whose key already exists, so re-running is safe.
 *
 * Also seeds the `dusk.test` fixture (route id 3, /dusk/{userId}) that
 * the Dusk browser suite depends on. Sequencing matters · the fixture
 * is installed third so it lands on page id 3 (after the first two
 * templates), keeping `/pages/3/edit` stable across reseed.
 */
class PageStudioDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Install the first two registered templates · keeps pages 1 + 2
        // anchored to the long-time blog-post + user-profile fixtures.
        $templates = TemplateRegistry::all();
        $rest = [];
        $count = 0;
        foreach ($templates as $slug => $class) {
            if ($count < 2) {
                $this->install($class);
                $count++;
            } else {
                $rest[$slug] = $class;
            }
        }

        // Drop the dusk-harness fixture in next so it lands on page id 3.
        $this->seedDuskFixture();

        // Then everything else.
        foreach ($rest as $class) {
            $this->install($class);
        }

        $this->seedDemoVariables();
    }

    protected function install(string $class): void
    {
        $routeData = $class::route();
        $routeName = (string) ($routeData['name'] ?? $class::name());

        if (RouteDefinition::where('name', $routeName)->exists()) {
            return;
        }

        foreach ($class::variables() as $v) {
            Variable::updateOrCreate(['name' => $v['name']], $v);
        }

        $route = RouteDefinition::create([
            'name'          => $routeName,
            'method'        => $routeData['method'] ?? 'GET',
            'path_template' => $routeData['path_template'] ?? '/',
            'description'   => $routeData['description'] ?? null,
        ]);

        foreach ($routeData['segments'] ?? [] as $s) {
            $varId = null;
            if (($s['kind'] ?? null) === 'variable' && ! empty($s['variable_name'])) {
                $varId = Variable::where('name', $s['variable_name'])->value('id');
            }
            $route->segments()->create([
                'position'      => $s['position'],
                'kind'          => $s['kind'],
                'literal_value' => $s['literal_value'] ?? null,
                'variable_id'   => $varId,
            ]);
        }

        if (! empty($class::blocks())) {
            Page::updateOrCreate(['route_id' => $route->id], ['blocks' => $class::blocks()]);
        }

        $graph = $class::graph();
        if (! empty($graph['nodes']) || ! empty($graph['edges'])) {
            NodeGraph::updateOrCreate(
                ['route_id' => $route->id],
                ['nodes' => $graph['nodes'] ?? [], 'edges' => $graph['edges'] ?? []],
            );
        }
    }

    /**
     * Seed the Dusk browser-suite's fixture · a /dusk/{userId} route with a
     * single paragraph block. The suite hard-codes `/pages/3/edit`, so this
     * is positioned third in the seed order.
     */
    protected function seedDuskFixture(): void
    {
        if (RouteDefinition::where('name', 'dusk.test')->exists()) {
            return;
        }

        $userId = Variable::updateOrCreate(['name' => 'userId'], [
            'name'        => 'userId',
            'label'       => 'User id',
            'type'        => 'int',
            'description' => 'Numeric user id for the Dusk harness route',
            'examples'    => ['1', '42', '1000'],
        ]);

        $route = RouteDefinition::create([
            'name'          => 'dusk.test',
            'method'        => 'GET',
            'path_template' => '/dusk/{userId}',
            'description'   => 'Dusk browser-suite fixture route',
        ]);

        $route->segments()->create([
            'position'      => 0,
            'kind'          => 'literal',
            'literal_value' => 'dusk',
            'variable_id'   => null,
        ]);
        $route->segments()->create([
            'position'      => 1,
            'kind'          => 'variable',
            'literal_value' => null,
            'variable_id'   => $userId->id,
        ]);

        Page::create([
            'route_id' => $route->id,
            'blocks'   => [
                [
                    'id'       => 'b_dusk_seed',
                    'type'     => 'paragraph',
                    'settings' => ['text' => 'Dusk harness page. Tests reset blocks before each run.'],
                ],
            ],
        ]);
    }

    protected function seedDemoVariables(): void
    {
        $vars = [
            [
                'name'        => 'category',
                'label'       => 'Category',
                'type'        => 'slug',
                'description' => 'Product or content category slug',
                'examples'    => ['electronics', 'books', 'clothing'],
            ],
            [
                'name'        => 'status',
                'label'       => 'Status',
                'type'        => 'enum',
                'description' => 'Workflow status of a record',
                'examples'    => ['draft', 'published', 'archived'],
            ],
            [
                'name'        => 'lang',
                'label'       => 'Language',
                'type'        => 'alpha',
                'description' => 'Two-letter language code',
                'examples'    => ['en', 'fr', 'de'],
            ],
            [
                'name'        => 'year',
                'label'       => 'Year',
                'type'        => 'int',
                'description' => 'Four-digit calendar year',
                'examples'    => ['2024', '2025', '2026'],
            ],
        ];

        foreach ($vars as $v) {
            if (Variable::where('name', $v['name'])->exists()) {
                continue;
            }
            Variable::create($v);
        }
    }
}
