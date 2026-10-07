<?php

use App\Enums\ArticleStatus;
use App\Jobs\FetchArticle;
use App\Livewire\Reader;
use App\Models\Article;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Queue::fake());

function readerUser(?string $role): User
{
    $user = User::factory()->create();

    if ($role) {
        $user->assignRole(Role::findOrCreate($role));
    }

    return $user;
}

function readyArticle(): Article
{
    return Article::factory()->create([
        'title' => 'Local-first software',
        'content_html' => '<h2>Ownership</h2><p>Cloud apps let people collaborate from anywhere. The network is an optional replication layer.</p><script>alert(1)</script>',
        'content_text' => 'Ownership Cloud apps let people collaborate from anywhere. The network is an optional replication layer.',
    ]);
}

it('redirects guests to login', function () {
    $this->get(route('articles.show', readyArticle()))->assertRedirect(route('filament.admin.auth.login'));
});

it('forbids users without a role', function () {
    $this->actingAs(readerUser(null))->get(route('articles.show', readyArticle()))->assertForbidden();
});

it('renders the stored article content for admin and demo users', function (string $role) {
    $article = readyArticle();

    $this->actingAs(readerUser($role))
        ->get(route('articles.show', $article))
        ->assertOk()
        ->assertSee('Local-first software')
        ->assertSee('<h2>Ownership</h2>', escape: false);
})->with(['admin', 'demo']);

it('shows fetch status instead of content for unfinished articles', function () {
    $queued = Article::factory()->queued()->create();
    $failed = Article::factory()->failed('HTTP 404')->create();
    $admin = readerUser('admin');

    Livewire::actingAs($admin)->test(Reader::class, ['article' => $queued])->assertSee('Fetching and extracting');
    Livewire::actingAs($admin)->test(Reader::class, ['article' => $failed])->assertSee('HTTP 404')->assertSee('RETRY NOW');
});

it('lets an admin highlight text from the article', function () {
    $article = readyArticle();

    Livewire::actingAs(readerUser('admin'))->test(Reader::class, ['article' => $article])
        ->call('addHighlight', 'The network is an optional replication layer.', 'collaborate from anywhere. ', '', 'green', 'Key idea', '#local-first #crdt')
        ->assertHasNoErrors()
        ->assertDispatched('highlights-changed')
        ->assertSee('Key idea');

    expect(Highlight::sole())
        ->exact->toBe('The network is an optional replication layer.')
        ->prefix->toBe('collaborate from anywhere. ')
        ->color->value->toBe('green')
        ->note->toBe('Key idea')
        ->tags->toBe(['local-first', 'crdt']);
});

it('accepts selections that span block elements, where the DOM has no space between blocks', function () {
    $article = readyArticle();

    Livewire::actingAs(readerUser('admin'))->test(Reader::class, ['article' => $article])
        ->call('addHighlight', 'OwnershipCloud apps', '', ' let people', 'cyan')
        ->assertHasNoErrors();

    expect(Highlight::count())->toBe(1);
});

it('rejects highlights of text that is not in the article', function () {
    Livewire::actingAs(readerUser('admin'))->test(Reader::class, ['article' => readyArticle()])
        ->call('addHighlight', 'Injected text that was never there', '', '', 'cyan')
        ->assertHasErrors('highlight');

    expect(Highlight::count())->toBe(0);
});

it('rejects invalid colors', function () {
    Livewire::actingAs(readerUser('admin'))->test(Reader::class, ['article' => readyArticle()])
        ->call('addHighlight', 'Cloud apps', '', '', 'red')
        ->assertHasErrors('color');
});

it('trims context to 32 characters', function () {
    Livewire::actingAs(readerUser('admin'))->test(Reader::class, ['article' => readyArticle()])
        ->call('addHighlight', 'Cloud apps', str_repeat('p', 60), str_repeat('s', 60), 'cyan');

    expect(Highlight::sole())->prefix->toHaveLength(32)->suffix->toHaveLength(32);
});

it('edits and deletes highlights', function () {
    $article = readyArticle();
    $highlight = Highlight::factory()->for($article)->create(['note' => 'old']);

    $test = Livewire::actingAs(readerUser('admin'))->test(Reader::class, ['article' => $article]);

    $test->call('updateNote', $highlight->id, 'new note');
    expect($highlight->fresh()->note)->toBe('new note');

    $test->call('deleteHighlight', $highlight->id)->assertDispatched('highlights-changed');
    expect(Highlight::count())->toBe(0);
});

it('cannot touch highlights that belong to another article', function () {
    $article = readyArticle();
    $other = Highlight::factory()->create();

    $test = Livewire::actingAs(readerUser('admin'))->test(Reader::class, ['article' => $article]);

    expect(fn () => $test->call('deleteHighlight', $other->id))->toThrow(ModelNotFoundException::class);

    expect($other->fresh())->not->toBeNull();
});

it('keeps the demo user read-only', function () {
    $article = readyArticle();
    $highlight = Highlight::factory()->for($article)->create();
    $demo = readerUser('demo');

    $attempts = [
        fn ($c) => $c->call('addHighlight', 'Cloud apps', '', '', 'cyan'),
        fn ($c) => $c->call('updateNote', $highlight->id, 'x'),
        fn ($c) => $c->call('deleteHighlight', $highlight->id),
        fn ($c) => $c->call('toggleRead'),
        fn ($c) => $c->call('toggleArchive'),
        fn ($c) => $c->call('retry'),
    ];

    foreach ($attempts as $attempt) {
        $attempt(Livewire::actingAs($demo)->test(Reader::class, ['article' => $article]))->assertForbidden();
    }

    expect(Highlight::count())->toBe(1)
        ->and($article->fresh())->read_at->toBeNull()->archived_at->toBeNull();
});

it('toggles read and archive state', function () {
    $article = readyArticle();
    $test = Livewire::actingAs(readerUser('admin'))->test(Reader::class, ['article' => $article]);

    $test->call('toggleRead');
    expect($article->fresh()->read_at)->not->toBeNull();

    $test->call('toggleArchive');
    expect($article->fresh()->archived_at)->not->toBeNull();
});

it('re-queues a failed article', function () {
    $article = Article::factory()->failed()->create();

    Livewire::actingAs(readerUser('admin'))->test(Reader::class, ['article' => $article])->call('retry');

    expect($article->fresh()->status)->toBe(ArticleStatus::Queued);
    Queue::assertPushed(FetchArticle::class);
});

it('links to the next unread article of the same user', function () {
    $admin = readerUser('admin');
    $current = readyArticle();
    $current->update(['user_id' => $admin->id]);
    Article::factory()->create(['title' => 'Not mine', 'created_at' => now()->subYear()]);
    Article::factory()->for($admin)->create(['title' => 'Up next']);

    Livewire::actingAs($admin)->test(Reader::class, ['article' => $current])
        ->assertSee('NEXT: Up next');
});

it('exports highlights as markdown', function () {
    $article = readyArticle();
    Highlight::factory()->for($article)->create(['exact' => 'Cloud apps let people collaborate', 'note' => 'Why it matters', 'tags' => ['crdt']]);

    $response = $this->actingAs(readerUser('demo'))->get(route('articles.highlights.export', $article));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="local-first-software.md"');

    expect($response->getContent())
        ->toStartWith("---\ntitle: \"Local-first software\"")
        ->toContain('source: "'.$article->url.'"')
        ->toContain("> Cloud apps let people collaborate\n\nWhy it matters\n\n#crdt");
});

it('forbids exporting for users without a role', function () {
    $this->actingAs(readerUser(null))
        ->get(route('articles.highlights.export', readyArticle()))
        ->assertForbidden();
});
