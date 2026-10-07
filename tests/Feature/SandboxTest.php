<?php

use App\Jobs\FetchArticle;
use App\Livewire\Library;
use App\Livewire\Reader;
use App\Models\Article;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Queue::fake();
    config([
        'stash.sandbox.template_email' => 'owner@example.com',
        'stash.sandbox.max_links' => 4,
        'stash.sandbox.max_active' => 3,
    ]);
});

/** The owner whose ready articles are copied into each sandbox. */
function sandboxTemplate(int $articles = 3): User
{
    $owner = User::factory()->create(['email' => 'owner@example.com'])->assignRole(Role::findOrCreate('admin'));
    Article::factory()->count($articles)->for($owner)->create()
        ->each(fn (Article $article) => Highlight::factory()->for($article)->create());

    return $owner;
}

function startSandbox(): User
{
    test()->post('/demo')->assertRedirect(route('library'));

    return auth()->user();
}

it('creates a private, expiring sandbox with sample articles and signs in', function () {
    sandboxTemplate();

    $guest = startSandbox();

    expect($guest)
        ->isSandbox()->toBeTrue()
        ->hasRole('guest')->toBeTrue()
        ->sandbox_expires_at->toBeBetween(now()->addHours(23), now()->addHours(25))
        ->and($guest->articles()->count())->toBe(3)
        ->and(Highlight::whereIn('article_id', $guest->articles()->select('id'))->count())->toBe(2);

    Queue::assertNothingPushed(); // copies are already fetched
    $this->get('/library')->assertOk()->assertSee('Private demo sandbox.')->assertSee('3 / 4 links');
});

it('works without a template, starting empty', function () {
    $guest = startSandbox();

    expect($guest->articles()->count())->toBe(0);
});

it('gives every visitor a separate sandbox', function () {
    $first = startSandbox();
    auth()->logout();
    $second = startSandbox();

    expect($first->id)->not->toBe($second->id);
});

it('lets a guest save, read, highlight and delete their own articles', function () {
    $guest = startSandbox();

    Livewire::actingAs($guest)->test(Library::class)
        ->set('url', 'https://example.com/post')->call('ingest')->assertHasNoErrors();
    $article = $guest->articles()->sole();
    Queue::assertPushed(FetchArticle::class);

    $article->update([
        'status' => 'ready',
        'content_html' => '<p>Guests can highlight this sentence.</p>',
        'content_text' => 'Guests can highlight this sentence.',
    ]);

    Livewire::actingAs($guest)->test(Reader::class, ['article' => $article])
        ->call('addHighlight', 'Guests can highlight this sentence.', '', '', 'green')
        ->assertHasNoErrors();
    expect($article->highlights()->count())->toBe(1);

    Livewire::actingAs($guest)->test(Library::class)->call('delete', $article->id);
    expect(Article::find($article->id))->toBeNull();
});

it('keeps sandboxes away from everyone else\'s data', function () {
    $owner = sandboxTemplate(1);
    $private = $owner->articles()->sole();
    $guest = startSandbox();

    $this->get(route('articles.show', $private))->assertForbidden();
    $this->get(route('articles.highlights.export', $private))->assertForbidden();
    $this->get('/library')->assertDontSee(route('articles.show', $private));

    Livewire::actingAs($guest)->test(Library::class)->call('delete', $private->id)->assertForbidden();
    expect($private->fresh())->not->toBeNull();
});

it('keeps guests out of the admin panel, collections and tokens', function () {
    startSandbox();

    $this->get('/admin')->assertForbidden();
    $this->get('/admin/collections')->assertForbidden();
    $this->get('/library')->assertDontSee('Admin panel')->assertDontSee('API tokens');
});

it('caps how many links a sandbox holds', function () {
    sandboxTemplate(3);
    $guest = startSandbox();

    $test = Livewire::actingAs($guest)->test(Library::class);
    $test->set('url', 'https://example.com/fourth')->call('ingest')->assertHasNoErrors();
    $test->set('url', 'https://example.com/fifth')->call('ingest')->assertHasErrors('url');

    expect($guest->articles()->count())->toBe(4);
});

it('refuses new sandboxes when too many are live', function () {
    foreach (range(1, 3) as $i) {
        startSandbox();
        auth()->logout();
    }

    $this->post('/demo')->assertStatus(503);
    $this->assertGuest();
});

it('deletes the sandbox and its data when the guest logs out', function () {
    sandboxTemplate();
    $guest = startSandbox();

    $this->post('/logout')->assertRedirect(route('home'));

    $this->assertGuest();
    expect(User::find($guest->id))->toBeNull()
        ->and(Article::where('user_id', $guest->id)->count())->toBe(0);
});

it('logging out a normal account keeps it', function () {
    $admin = User::factory()->create()->assignRole(Role::findOrCreate('admin'));

    $this->actingAs($admin)->post('/logout')->assertRedirect(route('home'));

    expect($admin->fresh())->not->toBeNull();
});

it('prunes expired sandboxes on schedule, with their articles and highlights', function () {
    sandboxTemplate();
    $expired = startSandbox();
    auth()->logout();
    $live = startSandbox();
    $expired->forceFill(['sandbox_expires_at' => now()->subMinute()])->save();

    $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

    expect(User::find($expired->id))->toBeNull()
        ->and(Article::where('user_id', $expired->id)->count())->toBe(0)
        ->and(User::find($live->id))->not->toBeNull()
        ->and(User::where('email', 'owner@example.com')->exists())->toBeTrue();
});

it('schedules the sandbox pruning hourly', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'model:prune') && str_contains($event->command, 'User'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *');
});

it('limits sandbox creation per visitor', function () {
    config(['stash.sandbox.max_active' => 100]);

    foreach (range(1, 5) as $i) {
        $this->post('/demo')->assertRedirect();
        auth()->logout();
    }

    $this->post('/demo')->assertStatus(429);
});
