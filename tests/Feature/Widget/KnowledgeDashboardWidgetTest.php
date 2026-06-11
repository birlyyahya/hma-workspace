<?php

use App\Models\SupportArticle;
use App\Models\SupportDocumentation;
use App\Models\SupportPolicy;
use App\Models\User;
use Livewire\Volt\Volt;

test('latest articles widget shows published articles and hides drafts', function () {
    $user = User::factory()->create();

    SupportArticle::create([
        'user_id' => $user->id,
        'title' => 'Panduan Real Terbaru',
        'content' => 'Isi artikel.',
        'category' => 'Guide',
        'is_published' => true,
    ]);

    SupportArticle::create([
        'user_id' => $user->id,
        'title' => 'Draft Tersembunyi',
        'content' => 'Belum publish.',
        'is_published' => false,
    ]);

    Volt::test('widget.dashboard.latest-articles')
        ->assertSee('Panduan Real Terbaru')
        ->assertDontSee('Draft Tersembunyi');
});

test('knowledge hub widget shows real active counts', function () {
    $user = User::factory()->create();

    SupportArticle::create(['user_id' => $user->id, 'title' => 'A1', 'content' => 'x', 'is_published' => true]);
    SupportPolicy::create(['title' => 'P1', 'content' => 'x', 'is_active' => true]);
    SupportPolicy::create(['title' => 'P2', 'content' => 'x', 'is_active' => false]);
    SupportDocumentation::create(['title' => 'D1', 'content' => 'x', 'is_active' => true]);

    Volt::test('widget.dashboard.knowledge-hub')
        ->assertSeeInOrder(['Documentation / Guides', 'Policies & Rules', 'Articles', 'Announcements'])
        ->assertSet('sections', function (array $sections) {
            $byRoute = collect($sections)->keyBy('route');

            expect($byRoute['knowledge.articles']['count'])->toBe(1)
                ->and($byRoute['knowledge.policies']['count'])->toBe(1)
                ->and($byRoute['knowledge.documentation']['count'])->toBe(1);

            return true;
        });
});
