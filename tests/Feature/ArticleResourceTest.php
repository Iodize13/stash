<?php

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Articles\Pages\ViewArticle;
use App\Filament\Resources\Articles\RelationManagers\HighlightsRelationManager;
use App\Jobs\FetchArticle;
use App\Models\Article;
use App\Models\Highlight;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Queue::fake());

function articleAdminUser(string $role): User
{
    return User::factory()->create(['name' => 'Panel '.$role])->assignRole(Role::findOrCreate($role));
}

it('lists and filters articles', function () {
    $ready = Article::factory()->create();
    $failed = Article::factory()->failed()->create();

    Livewire::actingAs(articleAdminUser('admin'))->test(ListArticles::class)
        ->assertCanSeeTableRecords([$ready, $failed])
        ->filterTable('status', 'failed')
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$ready]);
});

it('saves a link through the same path as the library', function () {
    $admin = articleAdminUser('admin');

    Livewire::actingAs($admin)->test(CreateArticle::class)
        ->fillForm(['url' => 'https://Example.com/post/?utm_source=x', 'tags' => ['Rust', '#systems']])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Article::sole())
        ->url->toBe('https://example.com/post')
        ->user_id->toBe($admin->id)
        ->status->toBe(ArticleStatus::Queued)
        ->tags->toBe(['rust', 'systems']);
    Queue::assertPushed(FetchArticle::class);
});

it('rejects duplicate links on create', function () {
    $admin = articleAdminUser('admin');
    Article::factory()->for($admin)->create(['url' => 'https://example.com/post', 'url_hash' => hash('sha256', 'https://example.com/post')]);

    Livewire::actingAs($admin)->test(CreateArticle::class)
        ->fillForm(['url' => 'https://example.com/post#intro'])
        ->call('create')
        ->assertHasFormErrors(['url']);

    expect(Article::count())->toBe(1);
});

it('normalizes tags on edit', function () {
    $article = Article::factory()->create();

    Livewire::actingAs(articleAdminUser('admin'))->test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->fillForm(['title' => 'Renamed', 'tags' => ['#Storage', 'storage', 'LSM']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->fresh())->title->toBe('Renamed')->tags->toBe(['storage', 'lsm']);
});

it('re-queues articles from the table', function () {
    $failed = Article::factory()->failed()->create();

    Livewire::actingAs(articleAdminUser('admin'))->test(ListArticles::class)
        ->callAction(TestAction::make('requeue')->table($failed));

    expect($failed->fresh()->status)->toBe(ArticleStatus::Queued);
    Queue::assertPushed(FetchArticle::class);
});

it('shows the change history, attributing queue updates to the system', function () {
    $admin = articleAdminUser('admin');
    $article = Article::factory()->queued()->create();
    $article->update(['status' => ArticleStatus::Ready, 'title' => 'Fetched title']);

    $this->actingAs($admin);
    $article->update(['tags' => ['edited']]);

    Livewire::actingAs($admin)->test(ViewArticle::class, ['record' => $article->getRouteKey()])
        ->assertSee('status: queued → ready')
        ->assertSee('title: — → Fetched title')
        ->assertSee('System')
        ->assertSee('tags: ')
        ->assertSee('Panel admin');
});

it('keeps demo users read-only', function () {
    $demo = articleAdminUser('demo');
    $article = Article::factory()->failed()->create();

    Livewire::actingAs($demo)->test(ListArticles::class)
        ->assertCanSeeTableRecords([$article])
        ->assertActionHidden('create')
        ->assertActionHidden(TestAction::make('requeue')->table($article))
        ->assertActionHidden(TestAction::make('edit')->table($article))
        ->assertActionHidden(TestAction::make('delete')->table($article));

    Livewire::actingAs($demo)->test(ViewArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionHidden('requeue')
        ->assertActionHidden('edit');

    $this->actingAs($demo)->get(ArticleResource::getUrl('edit', ['record' => $article]))->assertForbidden();
    $this->actingAs($demo)->get(ArticleResource::getUrl('create'))->assertForbidden();
    $this->actingAs($demo)->get(ArticleResource::getUrl('view', ['record' => $article]))->assertOk();
});

it('lets admins edit highlights but not demo users', function () {
    $article = Article::factory()->create();
    $highlight = Highlight::factory()->for($article)->create(['note' => 'old']);
    $params = ['ownerRecord' => $article, 'pageClass' => EditArticle::class];

    Livewire::actingAs(articleAdminUser('demo'))->test(HighlightsRelationManager::class, $params)
        ->assertCanSeeTableRecords([$highlight])
        ->assertActionHidden(TestAction::make('edit')->table($highlight))
        ->assertActionHidden(TestAction::make('delete')->table($highlight));

    Livewire::actingAs(articleAdminUser('admin'))->test(HighlightsRelationManager::class, $params)
        ->callAction(TestAction::make('edit')->table($highlight), ['note' => 'new note', 'color' => 'pink'])
        ->assertHasNoFormErrors();

    expect($highlight->fresh())->note->toBe('new note')->color->value->toBe('pink');
});
