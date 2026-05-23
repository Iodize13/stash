<?php

use App\Livewire\Highlights;
use App\Models\Article;
use App\Models\Highlight;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function highlightsUser(?string $role): User
{
    $user = User::factory()->create();

    if ($role) {
        $user->assignRole(Role::findOrCreate($role));
    }

    return $user;
}

it('requires a role', function () {
    $this->get('/highlights')->assertRedirect(route('filament.admin.auth.login'));
    $this->actingAs(highlightsUser(null))->get('/highlights')->assertForbidden();
});

it('lists highlights grouped by article with stats', function () {
    $article = Article::factory()->create(['title' => 'Epoll internals']);
    Highlight::factory()->for($article)->create(['exact' => 'Edge-triggered mode needs non-blocking sockets.', 'note' => 'Remember EAGAIN', 'color' => 'pink']);
    Highlight::factory()->for($article)->create(['exact' => 'Level-triggered is the default.', 'note' => null, 'tags' => []]);

    $this->actingAs(highlightsUser('demo'))->get('/highlights')
        ->assertOk()
        ->assertSee('2 HIGHLIGHTS ACROSS 1 ARTICLE')
        ->assertSee('Epoll internals')
        ->assertSee('Edge-triggered mode needs non-blocking sockets.')
        ->assertSee('Remember EAGAIN')
        ->assertSee('2 highlights · 1 note')
        ->assertSee('href="'.route('articles.show', $article).'#hl-', escape: false);
});

it('filters by search, color, notes and tags', function () {
    $a = Highlight::factory()->create(['exact' => 'Alpha quote', 'color' => 'cyan', 'note' => 'has note', 'tags' => ['storage']]);
    $b = Highlight::factory()->create(['exact' => 'Beta quote', 'color' => 'pink', 'note' => null, 'tags' => []]);

    $test = Livewire::actingAs(highlightsUser('admin'))->test(Highlights::class);

    $test->set('search', 'alpha')->assertSee('Alpha quote')->assertDontSee('Beta quote');
    $test->set('search', 'storage')->assertSee('Alpha quote')->assertDontSee('Beta quote');
    $test->set('search', '')->set('color', 'pink')->assertSee('Beta quote')->assertDontSee('Alpha quote');
    $test->set('color', '')->set('show', 'notes')->assertSee('Alpha quote')->assertDontSee('Beta quote');
    $test->set('show', 'untagged')->assertSee('Beta quote')->assertDontSee('Alpha quote');
});

it('shows a chronological view and an empty state', function () {
    $article = Article::factory()->create(['title' => 'Timeline article']);
    Highlight::factory()->for($article)->create(['exact' => 'First thing']);

    $test = Livewire::actingAs(highlightsUser('admin'))->test(Highlights::class)->set('group', 'time');
    $test->assertSee('First thing')->assertSee('Timeline article');

    $test->set('search', 'nothing-matches-this')->assertSee('No highlights match these filters.');
});

it('lets admins edit notes and delete, but not demo users', function () {
    $highlight = Highlight::factory()->create(['note' => 'old']);

    Livewire::actingAs(highlightsUser('admin'))->test(Highlights::class)
        ->call('updateNote', $highlight->id, 'new')
        ->assertHasNoErrors();
    expect($highlight->fresh()->note)->toBe('new');

    Livewire::actingAs(highlightsUser('demo'))->test(Highlights::class)
        ->assertDontSee('Edit note')
        ->call('delete', $highlight->id)
        ->assertForbidden();
    expect($highlight->fresh())->not->toBeNull();

    Livewire::actingAs(highlightsUser('admin'))->test(Highlights::class)->call('delete', $highlight->id);
    expect(Highlight::count())->toBe(0);
});

it('exports every highlight as markdown', function () {
    $article = Article::factory()->create(['title' => 'Exported article']);
    Highlight::factory()->for($article)->create(['exact' => 'Quoted line', 'note' => 'My note', 'tags' => []]);

    $response = $this->actingAs(highlightsUser('demo'))->get('/highlights.md')->assertOk();

    expect($response->getContent())
        ->toStartWith("---\ntitle: \"All highlights\"")
        ->toContain("## Exported article\n\nSource: <{$article->url}>\n\n> Quoted line\n\nMy note");
});

it('does not mark a palette color as active when no color filter is set', function () {
    Highlight::factory()->create(['color' => 'amber']);

    Livewire::actingAs(highlightsUser('admin'))->test(Highlights::class)
        ->assertDontSeeHtml('bg-amber-400 ring-2');
});
