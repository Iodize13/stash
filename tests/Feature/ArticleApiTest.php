<?php

use App\Enums\ArticleStatus;
use App\Jobs\FetchArticle;
use App\Livewire\ApiTokens;
use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Queue::fake());

function apiUser(string $role = 'admin'): User
{
    return User::factory()->create()->assignRole(Role::findOrCreate($role));
}

/** @param list<string> $abilities */
function apiToken(User $user, array $abilities = ['articles:read', 'articles:write']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

describe('POST /api/articles', function () {
    it('requires a token', function () {
        $this->postJson('/api/articles', ['url' => 'https://example.com/a'])->assertUnauthorized();
    });

    it('saves a link and returns the queued article', function () {
        $user = apiUser();

        $response = $this->withToken(apiToken($user))
            ->postJson('/api/articles', ['url' => 'https://Example.com/post?utm_source=x', 'title' => 'Hello', 'tags' => ['Rust', '#cli']])
            ->assertCreated()
            ->assertJsonPath('data.url', 'https://example.com/post')
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.title', 'Hello')
            ->assertJsonPath('data.tags', ['rust', 'cli'])
            ->assertJsonMissingPath('data.content_html')
            ->assertJsonMissingPath('data.content_text');

        $article = Article::sole();
        expect($article->user_id)->toBe($user->id);
        $response->assertHeader('Location', route('api.articles.show', $article));
        Queue::assertPushed(FetchArticle::class);
    });

    it('validates the link and rejects duplicates', function (array $body) {
        $user = apiUser();
        Article::factory()->for($user)->create(['url' => 'https://example.com/taken', 'url_hash' => hash('sha256', 'https://example.com/taken')]);

        $this->withToken(apiToken($user))->postJson('/api/articles', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($body)[0] === 'tags' ? 'tags' : 'url');
    })->with([
        'missing url' => [['title' => 'x']],
        'not http' => [['url' => 'javascript:alert(1)']],
        'duplicate' => [['url' => 'https://example.com/taken#section']],
        'tags not a list' => [['tags' => 'rust', 'url' => 'https://example.com/b']],
    ]);

    it('needs the write ability', function () {
        $this->withToken(apiToken(apiUser(), ['articles:read']))
            ->postJson('/api/articles', ['url' => 'https://example.com/a'])
            ->assertForbidden();

        expect(Article::count())->toBe(0);
    });

    it('refuses the read-only demo role even with a write token', function () {
        $this->withToken(apiToken(apiUser('demo')))
            ->postJson('/api/articles', ['url' => 'https://example.com/a'])
            ->assertForbidden();

        expect(Article::count())->toBe(0);
    });
});

describe('GET /api/articles', function () {
    it('lists only the token owner\'s articles, newest first, with filters', function () {
        $user = apiUser();
        $older = Article::factory()->for($user)->create(['created_at' => now()->subDay()]);
        $newer = Article::factory()->for($user)->failed()->create();
        Article::factory()->create(); // someone else's

        $token = apiToken($user);

        $this->withToken($token)->getJson('/api/articles')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.total', 2);

        $this->withToken($token)->getJson('/api/articles?status=failed')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', ArticleStatus::Failed->value);

        $this->withToken($token)->getJson('/api/articles?status=bogus')->assertUnprocessable();
    });

    it('shows one article and hides other people\'s', function () {
        $user = apiUser();
        $mine = Article::factory()->for($user)->create();
        $theirs = Article::factory()->create();
        $token = apiToken($user);

        $this->withToken($token)->getJson("/api/articles/{$mine->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id)
            ->assertJsonPath('data.links.reader', route('articles.show', $mine));

        $this->withToken($token)->getJson("/api/articles/{$theirs->id}")->assertNotFound();
    });

    it('needs the read ability', function () {
        $this->withToken(apiToken(apiUser(), ['articles:write']))->getJson('/api/articles')->assertForbidden();
    });

    it('is rate limited', function () {
        $this->withToken(apiToken(apiUser()))->getJson('/api/articles')
            ->assertHeader('X-RateLimit-Limit', '60');
    });
});

describe('/settings/tokens', function () {
    it('creates a token that works once and is shown only once', function () {
        $user = apiUser();

        $test = Livewire::actingAs($user)->test(ApiTokens::class)
            ->set('name', 'laptop')
            ->set('abilities', ['articles:write'])
            ->call('create')
            ->assertHasNoErrors();

        $plain = $test->get('plainTextToken');
        expect($plain)->toBeString()
            ->and(PersonalAccessToken::sole())->name->toBe('laptop')->abilities->toBe(['articles:write']);

        $this->withToken($plain)->postJson('/api/articles', ['url' => 'https://example.com/via-token'])->assertCreated();

        $test->call('dismissToken')->assertSet('plainTextToken', null)->assertDontSee($plain);
    });

    it('rejects unknown abilities', function () {
        Livewire::actingAs(apiUser())->test(ApiTokens::class)
            ->set('name', 'x')
            ->set('abilities', ['*'])
            ->call('create')
            ->assertHasErrors('abilities.0');
    });

    it('revokes tokens', function () {
        $user = apiUser();
        $token = apiToken($user);

        Livewire::actingAs($user)->test(ApiTokens::class)->call('revoke', PersonalAccessToken::sole()->id);

        expect(PersonalAccessToken::count())->toBe(0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/articles')->assertUnauthorized();
    });

    it('does not let the demo account create tokens', function () {
        $demo = apiUser('demo');

        $this->actingAs($demo)->get('/settings/tokens')->assertOk()->assertSee('cannot create tokens');

        Livewire::actingAs($demo)->test(ApiTokens::class)
            ->set('name', 'x')
            ->call('create')
            ->assertForbidden();

        expect(PersonalAccessToken::count())->toBe(0);
    });
});
