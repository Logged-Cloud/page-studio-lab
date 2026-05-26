<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Cross-tab live sync · proves the editor pulls peer edits via the
 * heartbeat and silently merges them into the local block tree.
 *
 * We don't open a literal second browser tab — that's expensive in
 * Dusk and the merge logic is what we actually want to lock down.
 * Instead we:
 *   1. Mount the editor in this browser (this is "Tab B").
 *   2. Write fresh blocks straight into the DB (this is "Tab A").
 *   3. Trigger Tab B's pullCollabUpdates immediately and assert the
 *      payload was merged into Livewire's `blocks` property.
 */
class CollabLiveSyncTest extends DuskTestCase
{
    public function test_peer_edits_in_the_db_appear_in_this_tab_after_a_collab_pull(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(500);

            // Seed Tab B with one heading block we can match on.
            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                wire.set('blocks', [
                    { id: 'h1', type: 'heading', settings: { text: 'Original heading', level: 'h1', align: 'left' } },
                ]);
            JS);
            $b->pause(700);

            // Tab A's write · the host-side test harness runs in the
            // SAME container Dusk is testing, so we can use the
            // package's models directly to mutate the DB row without
            // a shell hop (which broke on the {{ }} escape).
            $page = \LoggedCloud\PageStudio\Models\Page::find(3);
            $page->forceFill([
                'blocks'     => [['id' => 'h1', 'type' => 'heading', 'settings' => ['text' => 'Hello {{ userId }}', 'level' => 'h1', 'align' => 'left']]],
                'updated_at' => now()->addSeconds(10),
            ])->save();

            // Drive the merge directly · we don't want to wait the
            // full 8s heartbeat interval. The handler is wired via
            // the same code path the interval would take.
            $b->script(<<<'JS'
                const root = document.querySelector('[x-data*="pageStudioPageBuilder"]')
                          || document.querySelector('[data-component="page-studio.page-builder"]');
                const scope = Alpine.$data(root);
                window.__collabResult = await scope.$wire.pullCollabUpdates(null);
                if (window.__collabResult) {
                    scope.applyCollabUpdate(window.__collabResult);
                    scope.lastSyncIso = window.__collabResult.updatedAt;
                }
            JS);
            $b->pause(800);

            $text = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                    .get('blocks')[0].settings.text;
            JS)[0];

            $this->assertSame('Hello {{ userId }}', (string) $text,
                'Tab B should have merged Tab A\'s DB write on the next collab pull · got '.var_export($text, true));
        });
    }

    public function test_collab_merge_is_skipped_while_an_input_is_focused(): void
    {
        // Type-defence · if the user is mid-keystroke, the silent
        // merge MUST NOT overwrite what they're typing.
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)
                ->visit('/pages/3/edit')
                ->waitFor('[data-component="page-studio.page-builder"]', 5);
            $b->pause(500);

            $b->script(<<<'JS'
                const wire = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
                wire.set('blocks', [
                    { id: 'h1', type: 'heading', settings: { text: 'Locally typed', level: 'h1', align: 'left' } },
                ]);
            JS);
            $b->pause(600);

            // Mount a hidden <input> and focus it so applyCollabUpdate
            // detects "user is typing" and bails.
            $b->script(<<<'JS'
                const inp = document.createElement('input');
                inp.id = '__focus_guard_input';
                inp.type = 'text';
                document.body.appendChild(inp);
                inp.focus();

                const root = document.querySelector('[x-data*="pageStudioPageBuilder"]')
                          || document.querySelector('[data-component="page-studio.page-builder"]');
                const scope = Alpine.$data(root);

                // Simulate a peer payload arriving · the merge should
                // be skipped because an input has focus.
                scope.applyCollabUpdate({
                    blocks: [{ id: 'h1', type: 'heading', settings: { text: 'Peer overwrite', level: 'h1', align: 'left' } }],
                    meta:   {},
                    updatedAt: new Date().toISOString(),
                });
            JS);
            $b->pause(500);

            $text = $b->script(<<<'JS'
                return Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                    .get('blocks')[0].settings.text;
            JS)[0];

            $this->assertSame('Locally typed', (string) $text,
                'merge MUST be skipped while an editable input has focus · got '.var_export($text, true));
        });
    }
}
