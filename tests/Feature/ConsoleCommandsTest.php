<?php

use App\Console\Commands\DemoContent;
use App\Enums\ArticleStatus;
use App\Jobs\FetchArticle;
use App\Models\Article;
use App\Models\Collection;
use App\Models\User;
use App\Support\UrlNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

it('creates an admin with a generated password', function () {
    $this->artisan('stash:create-user', ['email' => 'owner@example.com'])
        ->expectsOutputToContain('admin user owner@example.com is ready.')
        ->expectsOutputToContain('Password: ')
        ->assertSuccessful();

    expect(User::sole())->name->toBe('owner')->hasRole('admin')->toBeTrue();
});

it('updates an existing user with a given password and role', function () {
    User::factory()->create(['email' => 'demo@example.com']);

    $this->artisan('stash:create-user', ['email' => 'demo@example.com', '--role' => 'demo', '--password' => 'secret-pass'])
        ->doesntExpectOutputToContain('Password: ')
        ->assertSuccessful();

    $user = User::sole();
    expect($user->hasRole('demo'))->toBeTrue()
        ->and(Hash::check('secret-pass', $user->password))->toBeTrue();
});

it('rejects unknown roles and bad emails', function () {
    $this->artisan('stash:create-user', ['email' => 'a@example.com', '--role' => 'root'])->assertFailed();
    $this->artisan('stash:create-user', ['email' => 'nope'])->assertFailed();

    expect(User::count())->toBe(0);
});

it('saves the curated links once and queues them', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('stash:demo-content', ['email' => 'owner@example.com'])->assertSuccessful();
    $this->artisan('stash:demo-content', ['email' => 'owner@example.com'])->expectsOutputToContain('already saved')->assertSuccessful();

    expect($owner->articles()->count())->toBe(count(DemoContent::LINKS));
    Queue::assertPushed(FetchArticle::class, count(DemoContent::LINKS));
});

it('highlights fetched articles and publishes a collection', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $url = array_key_first(DemoContent::LINKS);
    $text = 'In computer science, the log-structured merge-tree is a data structure with performance characteristics that make it attractive for write-heavy workloads. It keeps data in memory before flushing sorted runs to disk in the background.';

    Article::factory()->for($owner)->create([
        'url' => $url,
        'url_hash' => UrlNormalizer::hash(UrlNormalizer::normalize($url)),
        'status' => ArticleStatus::Ready,
        'content_text' => 'Log-structured merge-tree TypeTree Invented 1996 '.$text,
        'content_html' => '<table><tr><td>TypeTree Invented 1996</td></tr></table><p>'.$text.'</p>',
    ]);

    $this->artisan('stash:demo-content', ['email' => 'owner@example.com', '--highlight' => true])
        ->expectsOutputToContain('/c/systems-reading-list')
        ->assertSuccessful();

    $article = Article::sole();
    expect($article->highlights)->toHaveCount(2)
        ->and($article->highlights->first()->exact)->toStartWith('In computer science, the log-structured merge-tree')
        ->and(Collection::sole())->is_public->toBeTrue()->articles->toHaveCount(1);

    // Running again does not duplicate anything.
    $this->artisan('stash:demo-content', ['email' => 'owner@example.com', '--highlight' => true])->assertSuccessful();
    expect($article->highlights()->count())->toBe(2);
});

it('needs an existing owner', function () {
    $this->artisan('stash:demo-content', ['email' => 'missing@example.com'])->assertFailed();
});
