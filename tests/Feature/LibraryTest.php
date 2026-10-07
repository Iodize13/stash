<?php

use App\Enums\ArticleStatus;
use App\Jobs\FetchArticle;
use App\Livewire\Library;
use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Queue::fake());

function libraryUser(?string $role): User
{
    $user = User::factory()->create();

    if ($role) {
        $user->assignRole(Role::findOrCreate($role));
    }

    return $user;
}

it('redirects guests to the login page', function () {
    $this->get('/library')->assertRedirect(route('filament.admin.auth.login'));
});

it('forbids users without a role', function () {
    $this->actingAs(libraryUser(null))->get('/library')->assertForbidden();
});

it('shows each user only their own articles', function (string $role) {
    $user = libraryUser($role);
    Article::factory()->for($user)->create(['title' => 'Epoll internals']);
    Article::factory()->create(['title' => 'Someone else entirely']);

    $this->actingAs($user)
        ->get('/library')
        ->assertOk()
        ->assertSee('Epoll internals')
        ->assertDontSee('Someone else entirely');
})->with(['admin', 'demo', 'guest']);

it('lets an admin save a link as a queued article with normalized url and tags', function () {
    $admin = libraryUser('admin');

    Livewire::actingAs($admin)->test(Library::class)
        ->set('url', 'https://Example.com/post/?utm_source=x')
        ->set('tags', '#Systems #rust')
        ->call('ingest')
        ->assertHasNoErrors()
        ->assertSet('url', '');

    $article = Article::sole();
    expect($article)
        ->url->toBe('https://example.com/post')
        ->domain->toBe('example.com')
        ->status->toBe(ArticleStatus::Queued)
        ->tags->toBe(['systems', 'rust'])
        ->user_id->toBe($admin->id);

    Queue::assertPushed(FetchArticle::class, fn (FetchArticle $job) => $job->article->is($article));
});

it('rejects a link already saved in another form', function () {
    Livewire::actingAs(libraryUser('admin'))->test(Library::class)
        ->set('url', 'https://example.com/post')->call('ingest')
        ->set('url', 'https://example.com/post/?utm_campaign=y#top')->call('ingest')
        ->assertHasErrors('url');

    expect(Article::count())->toBe(1);
});

it('rejects invalid links', function (string $url) {
    Livewire::actingAs(libraryUser('admin'))->test(Library::class)
        ->set('url', $url)->call('ingest')
        ->assertHasErrors('url');

    expect(Article::count())->toBe(0);
})->with(['not a url', 'javascript:alert(1)', 'ftp://example.com/x']);

it('keeps the demo user read-only', function () {
    $article = Article::factory()->failed()->create();

    $demo = libraryUser('demo');

    Livewire::actingAs($demo)->test(Library::class)
        ->assertDontSee('Save a link')
        ->assertDontSee('RETRY NOW');

    // A forbidden call ends the component's request cycle, so each action gets a fresh component.
    $attempts = [
        fn ($c) => $c->set('url', 'https://example.com/x')->call('ingest'),
        fn ($c) => $c->call('delete', $article->id),
        fn ($c) => $c->call('retry', $article->id),
        fn ($c) => $c->call('toggleArchive', $article->id),
        fn ($c) => $c->call('retryFailed'),
        fn ($c) => $c->call('markVisibleRead'),
    ];

    foreach ($attempts as $attempt) {
        $attempt(Livewire::actingAs($demo)->test(Library::class))->assertForbidden();
    }

    expect($article->fresh()->status)->toBe(ArticleStatus::Failed);
});

it('filters by tab and status', function () {
    $admin = libraryUser('admin');
    Article::factory()->for($admin)->create(['title' => 'Unread one']);
    Article::factory()->for($admin)->read()->create(['title' => 'Already read']);
    Article::factory()->for($admin)->archived()->create(['title' => 'Old archived']);
    Article::factory()->for($admin)->failed()->create(['title' => 'Broken one']);

    $test = Livewire::actingAs($admin)->test(Library::class);

    $test->set('tab', 'unread')->assertSee('Unread one')->assertDontSee('Already read')->assertDontSee('Old archived');
    $test->set('tab', 'archived')->assertSee('Old archived')->assertDontSee('Unread one');
    $test->set('tab', 'all')->set('status', 'failed')->assertSee('Broken one')->assertDontSee('Already read');
});

it('searches titles, domains and tags', function () {
    $admin = libraryUser('admin');
    Article::factory()->for($admin)->create(['title' => 'LSM trees explained', 'tags' => ['storage'], 'url' => 'https://a.test/1', 'excerpt' => null]);
    Article::factory()->for($admin)->create(['title' => 'Something else', 'tags' => ['llm'], 'url' => 'https://b.test/2', 'excerpt' => null]);

    $test = Livewire::actingAs($admin)->test(Library::class);

    $test->set('search', 'lsm')->assertSee('LSM trees explained')->assertDontSee('Something else');
    $test->set('search', 'llm')->assertSee('Something else')->assertDontSee('LSM trees explained');
});

it('treats LIKE wildcards in search literally', function () {
    Article::factory()->create(['title' => 'Plain title']);

    Livewire::actingAs(libraryUser('admin'))->test(Library::class)
        ->set('search', '%')
        ->assertDontSee('Plain title');
});

it('retries, archives and deletes as admin', function () {
    $failed = Article::factory()->failed()->create();
    $other = Article::factory()->create();

    $test = Livewire::actingAs(libraryUser('admin'))->test(Library::class);

    $test->call('retry', $failed->id);
    expect($failed->fresh())->status->toBe(ArticleStatus::Queued)->error->toBeNull();
    Queue::assertPushed(FetchArticle::class, fn (FetchArticle $job) => $job->article->is($failed));

    $test->call('toggleArchive', $other->id);
    expect($other->fresh()->archived_at)->not->toBeNull();
    $test->call('toggleArchive', $other->id);
    expect($other->fresh()->archived_at)->toBeNull();

    $test->call('delete', $other->id);
    expect(Article::find($other->id))->toBeNull();
});

it('paginates ten articles per page', function () {
    $admin = libraryUser('admin');
    Article::factory()->count(12)->for($admin)->create();

    Livewire::actingAs($admin)->test(Library::class)
        ->assertSee('Showing 1 – 10 of 12')
        ->call('gotoPage', 2)
        ->assertSee('Showing 11 – 12 of 12');
});

it('links each card to the reader', function () {
    $user = libraryUser('admin');
    $article = Article::factory()->for($user)->create();

    Livewire::actingAs($user)->test(Library::class)
        ->assertSeeHtml('href="'.route('articles.show', $article).'"');
});
