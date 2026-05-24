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

function popupUser(string $role): User
{
    return User::factory()->create()->assignRole(Role::findOrCreate($role));
}

it('sends guests to log in and back to the popup', function () {
    $this->get('/save?url=https://example.com/a')
        ->assertRedirect(route('filament.admin.auth.login'));

    expect(urldecode(session('url.intended')))->toBe(url('/save?url=https://example.com/a'));
});

it('asks for confirmation instead of saving on GET', function () {
    $this->actingAs(popupUser('admin'))
        ->get('/save?url=https://example.com/post&title=Great+post')
        ->assertOk()
        ->assertSee('Great post')
        ->assertSee('https://example.com/post')
        ->assertSee('Save link');

    expect(Article::count())->toBe(0);
});

it('saves the link with its page title and tags, then confirms', function () {
    $admin = popupUser('admin');

    $this->actingAs($admin)
        ->post('/save', ['url' => 'https://Example.com/post?utm_source=x', 'title' => 'Great post', 'tags' => '#rust'])
        ->assertRedirect(route('save'));

    expect(Article::sole())
        ->url->toBe('https://example.com/post')
        ->title->toBe('Great post')
        ->tags->toBe(['rust'])
        ->status->toBe(ArticleStatus::Queued)
        ->user_id->toBe($admin->id);
    Queue::assertPushed(FetchArticle::class);

    $this->actingAs($admin)->get(route('save'))->assertSee('Saved. Fetching it now.');
});

it('tells you when the link is already saved', function () {
    $admin = popupUser('admin');
    $article = Article::factory()->for($admin)->create(['url' => 'https://example.com/post', 'url_hash' => hash('sha256', 'https://example.com/post')]);

    $this->actingAs($admin)
        ->get('/save?url=https://example.com/post%23intro')
        ->assertSee('Already in your library.')
        ->assertSee(route('articles.show', $article));
});

it('shows validation errors for bad links', function () {
    $this->actingAs(popupUser('admin'))
        ->from('/save?url=javascript:alert(1)')
        ->post('/save', ['url' => 'javascript:alert(1)'])
        ->assertRedirect('/save?url=javascript:alert(1)')
        ->assertSessionHasErrors('url');

    expect(Article::count())->toBe(0);
});

it('keeps the demo user read-only', function () {
    $demo = popupUser('demo');

    $this->actingAs($demo)->get('/save?url=https://example.com/a')->assertSee('This account is read-only');
    $this->actingAs($demo)->post('/save', ['url' => 'https://example.com/a'])->assertForbidden();

    expect(Article::count())->toBe(0);
});

it('offers the bookmarklet to people who can save', function () {
    $bookmarklet = "javascript:(()=>{window.open('".route('save').'?url=';

    $this->actingAs(popupUser('admin'))->get('/save')->assertSee($bookmarklet, escape: false);

    Livewire::actingAs(popupUser('admin'))->test(Library::class)->assertSeeHtml($bookmarklet);
    Livewire::actingAs(popupUser('demo'))->test(Library::class)->assertDontSeeHtml('javascript:');
});
