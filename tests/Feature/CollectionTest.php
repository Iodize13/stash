<?php

use App\Filament\Resources\Collections\CollectionResource;
use App\Filament\Resources\Collections\Pages\CreateCollection;
use App\Filament\Resources\Collections\Pages\EditCollection;
use App\Filament\Resources\Collections\Pages\ListCollections;
use App\Filament\Resources\Collections\RelationManagers\ArticlesRelationManager;
use App\Models\Article;
use App\Models\Collection;
use App\Models\Highlight;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function collectionUser(string $role): User
{
    return User::factory()->create(['name' => 'Ada Curator'])->assignRole(Role::findOrCreate($role));
}

/**
 * A public collection with one article that has a curator note and a highlight.
 */
function publishedCollection(): Collection
{
    $article = Article::factory()->create([
        'title' => 'Log-structured merge trees',
        'tags' => ['storage', 'lsm'],
        'content_text' => 'PRIVATE-BODY-TEXT Write amplification matters on SSDs.',
        'content_html' => '<p>PRIVATE-BODY-TEXT Write amplification matters on SSDs.</p>',
    ]);
    Highlight::factory()->for($article)->create(['exact' => 'Write amplification matters on SSDs.', 'note' => 'Key trade-off']);

    $collection = Collection::factory()->public()->for(collectionUser('admin'))->create([
        'title' => 'Storage engines',
        'slug' => 'storage-engines',
    ]);
    $collection->articles()->attach($article, ['note' => 'Start here.']);

    return $collection;
}

describe('public page', function () {
    it('is visible to guests and shows titles, notes and highlights', function () {
        publishedCollection();

        $this->get('/c/storage-engines')
            ->assertOk()
            ->assertSee('Storage engines')
            ->assertSee('Ada Curator')
            ->assertSee('Log-structured merge trees')
            ->assertSee('Start here.')
            ->assertSee('Write amplification matters on SSDs.')
            ->assertSee('Key trade-off')
            ->assertSee('#storage (1)');
    });

    it('never publishes the stored article text', function () {
        publishedCollection();

        $this->get('/c/storage-engines')->assertDontSee('PRIVATE-BODY-TEXT');
        $this->get('/c/storage-engines/feed.atom')->assertDontSee('PRIVATE-BODY-TEXT');
        $this->get('/c/storage-engines/highlights.md')->assertDontSee('PRIVATE-BODY-TEXT');
    });

    it('returns 404 for private or unknown collections', function (string $path) {
        Collection::factory()->create(['slug' => 'secret']);

        $this->get($path)->assertNotFound();
    })->with(['/c/secret', '/c/secret/feed.atom', '/c/secret/highlights.md', '/c/missing']);

    it('serves a valid Atom feed', function () {
        publishedCollection();

        $response = $this->get('/c/storage-engines/feed.atom')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/atom+xml; charset=UTF-8');

        $feed = simplexml_load_string($response->getContent());
        expect($feed)->not->toBeFalse()
            ->and((string) $feed->title)->toBe('Storage engines')
            ->and($feed->entry)->toHaveCount(1)
            ->and((string) $feed->entry[0]->title)->toBe('Log-structured merge trees')
            ->and((string) $feed->entry[0]->content)->toContain('<blockquote>Write amplification matters on SSDs.</blockquote>');
    });

    it('exports the collection as markdown', function () {
        publishedCollection();

        $response = $this->get('/c/storage-engines/highlights.md')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="storage-engines.md"');

        expect($response->getContent())
            ->toStartWith("---\ntitle: \"Storage engines\"\ncurator: \"Ada Curator\"")
            ->toContain("## Log-structured merge trees\n\nSource: <")
            ->toContain("Start here.\n\n> Write amplification matters on SSDs.\n\nKey trade-off");
    });
});

describe('admin panel', function () {
    it('lets an admin create a collection owned by them', function () {
        $admin = collectionUser('admin');

        Livewire::actingAs($admin)->test(CreateCollection::class)
            ->fillForm(['title' => 'Reading list', 'slug' => 'reading-list', 'is_public' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Collection::sole())
            ->user_id->toBe($admin->id)
            ->slug->toBe('reading-list')
            ->is_public->toBeTrue();
    });

    it('rejects duplicate and invalid slugs', function (string $slug) {
        Collection::factory()->create(['slug' => 'taken']);

        Livewire::actingAs(collectionUser('admin'))->test(CreateCollection::class)
            ->fillForm(['title' => 'X', 'slug' => $slug])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    })->with(['taken', 'has spaces', 'bad/slash']);

    it('attaches articles with a curator note', function () {
        $admin = collectionUser('admin');
        $collection = Collection::factory()->for($admin)->create();
        $article = Article::factory()->create();

        Livewire::actingAs($admin)
            ->test(ArticlesRelationManager::class, ['ownerRecord' => $collection, 'pageClass' => EditCollection::class])
            ->callAction(TestAction::make('attach')->table(), ['recordId' => $article->id, 'note' => 'Why this matters'])
            ->assertHasNoFormErrors();

        expect($collection->articles()->sole())
            ->id->toBe($article->id)
            ->pivot->note->toBe('Why this matters');
    });

    it('keeps demo users read-only', function () {
        $demo = collectionUser('demo');
        $collection = Collection::factory()->create();
        $collection->articles()->attach(Article::factory()->create());

        Livewire::actingAs($demo)->test(ListCollections::class)
            ->assertCanSeeTableRecords([$collection])
            ->assertActionHidden('create')
            ->assertActionHidden(TestAction::make('edit')->table($collection));

        $this->actingAs($demo)->get(CollectionResource::getUrl('edit', ['record' => $collection]))->assertForbidden();
        $this->actingAs($demo)->get(CollectionResource::getUrl('create'))->assertForbidden();
        $this->actingAs($demo)->get(CollectionResource::getUrl('view', ['record' => $collection]))->assertOk();
    });

    it('does not let demo users attach or detach articles', function () {
        $collection = Collection::factory()->create();
        $attached = Article::factory()->create();
        $collection->articles()->attach($attached);

        Livewire::actingAs(collectionUser('demo'))
            ->test(ArticlesRelationManager::class, ['ownerRecord' => $collection, 'pageClass' => EditCollection::class])
            ->assertActionHidden(TestAction::make('attach')->table())
            ->assertActionHidden(TestAction::make('edit')->table($attached))
            ->assertActionHidden(TestAction::make('detach')->table($attached));
    });
});
