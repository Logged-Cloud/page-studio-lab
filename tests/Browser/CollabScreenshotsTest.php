<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use LoggedCloud\PageStudio\Models\Activity;
use LoggedCloud\PageStudio\Models\BlockComment;
use LoggedCloud\PageStudio\Models\BlockLock;
use LoggedCloud\PageStudio\Models\Presence;
use Tests\DuskTestCase;

class CollabScreenshotsTest extends DuskTestCase
{
    protected function freshLab(Browser $b): void
    {
        BlockLock::query()->delete();
        Presence::query()->delete();
        BlockComment::query()->delete();
        Activity::query()->delete();

        // Persist blocks to the Page row so the refresh + DB-driven lock /
        // comment rows render against actual canvas blocks. Setting via
        // Livewire alone wipes on refresh.
        \LoggedCloud\PageStudio\Models\Page::where('route_id', 3)->update([
            'blocks' => [
                ['id' => 'b-head', 'type' => 'heading',   'settings' => ['text' => 'Build pages, ship faster', 'level' => 'h1', 'align' => 'left']],
                ['id' => 'b-para', 'type' => 'paragraph', 'settings' => ['text' => 'A short pitch for the product. Two sentences max.']],
                ['id' => 'b-cta',  'type' => 'button',    'settings' => ['label' => 'Get started', 'href' => '/signup', 'variant' => 'primary']],
            ],
        ]);

        $b->resize(1440, 900)
            ->visit('/pages/3/edit')
            ->waitFor('[data-component="page-studio.page-builder"]', 5);
        $b->pause(500);
    }

    public function test_block_lock_ribbon(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshLab($b);

            BlockLock::create([
                'page_id'     => 3,
                'block_id'    => 'b-head',
                'author_id'   => 999,
                'author_name' => 'Alice',
                'expires_at'  => now()->addMinutes(5),
            ]);

            $b->refresh()
                ->waitFor('[data-component="page-studio.page-builder"]', 5)
                ->pause(900)
                ->screenshot('collab-block-lock');
        });
    }

    public function test_presence_chips(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshLab($b);

            Presence::create([
                'page_id' => 3, 'author_id' => 1001, 'author_name' => 'Alice',
                'session_id' => 'sess-alice', 'seen_at' => now()->subSeconds(5),
            ]);
            Presence::create([
                'page_id' => 3, 'author_id' => 1002, 'author_name' => 'Bob',
                'session_id' => 'sess-bob', 'seen_at' => now()->subSeconds(2),
            ]);

            $b->refresh()
                ->waitFor('[data-component="page-studio.page-builder"]', 5)
                ->pause(900)
                ->screenshot('collab-presence');
        });
    }

    public function test_comments_thread(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshLab($b);

            $parent = BlockComment::create([
                'page_id'    => 3,
                'block_id'   => 'b-head',
                'parent_id'  => null,
                'author_id'  => 1001,
                'author_name'=> 'Alice',
                'body'       => 'Can we make this heading more punchy? Maybe lead with the value prop.',
            ]);
            BlockComment::create([
                'page_id'    => 3,
                'block_id'   => 'b-head',
                'parent_id'  => $parent->id,
                'author_id'  => 1002,
                'author_name'=> 'Bob',
                'body'       => 'Good call. How about "Build pages, ship faster"?',
            ]);

            $b->refresh()
                ->waitFor('[data-component="page-studio.page-builder"]', 5)
                ->pause(900);

            // Select the heading block first · the right rail only renders
            // when a block is selected OR a non-Settings tab is active.
            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('selectedPath', '0');");
            $b->pause(700);

            $b->script(<<<'JS'
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                if (root) Alpine.$data(root).rightTab = 'comments';
            JS);
            $b->pause(1100)->screenshot('collab-comments');
        });
    }

    public function test_activity_feed(): void
    {
        $this->browse(function (Browser $b) {
            $this->freshLab($b);

            $now = now();
            Activity::create(['page_id' => 3, 'route_id' => 3, 'verb' => 'saved',         'author_name' => 'Charles', 'created_at' => $now->copy()->subMinutes(2),  'updated_at' => $now]);
            Activity::create(['page_id' => 3, 'route_id' => 3, 'verb' => 'comment_added', 'author_name' => 'Alice',   'payload' => ['block_id' => 'b-head', 'body' => 'punchier please'], 'created_at' => $now->copy()->subMinutes(8),  'updated_at' => $now]);
            Activity::create(['page_id' => 3, 'route_id' => 3, 'verb' => 'published',     'author_name' => 'Bob',     'created_at' => $now->copy()->subMinutes(15), 'updated_at' => $now]);
            Activity::create(['page_id' => 3, 'route_id' => 3, 'verb' => 'lock_acquired', 'author_name' => 'Alice',   'payload' => ['block_id' => 'b-head'], 'created_at' => $now->copy()->subMinutes(20), 'updated_at' => $now]);
            Activity::create(['page_id' => 3, 'route_id' => 3, 'verb' => 'saved',         'author_name' => 'Charles', 'created_at' => $now->copy()->subHours(1),    'updated_at' => $now]);

            $b->refresh()
                ->waitFor('[data-component="page-studio.page-builder"]', 5)
                ->pause(900);

            $b->script("Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id')).set('selectedPath', '0');");
            $b->pause(700);

            $b->script(<<<'JS'
                const root = document.querySelector('[x-data="pageStudioPageBuilder()"]');
                if (root) Alpine.$data(root).rightTab = 'activity';
            JS);
            $b->pause(1100)->screenshot('collab-activity');
        });
    }
}
