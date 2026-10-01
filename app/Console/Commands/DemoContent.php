<?php

namespace App\Console\Commands;

use App\Actions\SaveLink;
use App\Enums\ArticleStatus;
use App\Enums\HighlightColor;
use App\Models\Article;
use App\Models\Collection;
use App\Models\Highlight;
use App\Models\User;
use App\Support\UrlNormalizer;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Fills a library with real articles for demos and screen recordings.
 *
 * Run once to save the links (the queue fetches them), then again with
 * --highlight once they are fetched to add highlights and a public collection.
 */
class DemoContent extends Command
{
    /** url => [tags, note shown on the first highlight and in the collection] */
    public const LINKS = [
        'https://en.wikipedia.org/wiki/Log-structured_merge-tree' => [['storage', 'databases'], 'Why RocksDB and Cassandra turn random writes into sequential ones.'],
        'https://en.wikipedia.org/wiki/B-tree' => [['storage', 'databases'], 'The read-optimised counterpart to LSM trees; still the default index in Postgres.'],
        'https://en.wikipedia.org/wiki/Bloom_filter' => [['data-structures'], 'How LSM engines skip SSTables that cannot contain a key.'],
        'https://en.wikipedia.org/wiki/Skip_list' => [['data-structures'], 'The ordered structure behind many memtables, e.g. LevelDB.'],
        'https://en.wikipedia.org/wiki/Write-ahead_logging' => [['storage', 'durability'], 'Durability first: log the change before touching the data pages.'],
        'https://en.wikipedia.org/wiki/Consistent_hashing' => [['distributed-systems'], 'Adding a node only moves about 1/n of the keys.'],
        'https://en.wikipedia.org/wiki/Raft_(algorithm)' => [['distributed-systems', 'consensus'], 'Consensus designed to be understandable: leader election plus log replication.'],
        'https://en.wikipedia.org/wiki/Conflict-free_replicated_data_type' => [['distributed-systems', 'local-first'], 'Replicas that merge without coordination, the basis of local-first apps.'],
    ];

    protected $signature = 'stash:demo-content
        {email : Owner of the demo articles}
        {--highlight : Add highlights and a public collection to articles that are already fetched}';

    protected $description = 'Save curated real articles (and, with --highlight, highlights and a public collection)';

    public function handle(SaveLink $saveLink): int
    {
        $owner = User::where('email', $this->argument('email'))->first();

        if (! $owner) {
            $this->error('No user with that email. Create one with stash:create-user first.');

            return self::FAILURE;
        }

        return $this->option('highlight') ? $this->highlight($owner) : $this->save($owner, $saveLink);
    }

    private function save(User $owner, SaveLink $saveLink): int
    {
        foreach (self::LINKS as $url => [$tags]) {
            try {
                $saveLink->handle($owner, $url, implode(' ', $tags));
                $this->line("queued  {$url}");
            } catch (ValidationException) {
                $this->line("skipped {$url} (already saved)");
            }
        }

        $this->info('Links saved. Once the queue has fetched them, run again with --highlight.');

        return self::SUCCESS;
    }

    private function highlight(User $owner): int
    {
        $colors = HighlightColor::cases();
        $collection = Collection::firstOrCreate(
            ['slug' => 'systems-reading-list'],
            [
                'user_id' => $owner->id,
                'title' => 'Systems reading list',
                'description' => 'Storage engines, data structures and distributed systems, with the passages worth remembering.',
                'is_public' => true,
            ],
        );

        $position = 0;

        foreach (self::LINKS as $url => [, $note]) {
            $article = $owner->articles()
                ->where('url_hash', UrlNormalizer::hash(UrlNormalizer::normalize($url)))
                ->where('status', ArticleStatus::Ready)
                ->first();

            if (! $article) {
                $this->warn("not ready {$url}");

                continue;
            }

            if (! $article->highlights()->exists()) {
                foreach ($this->sentences($article) as $i => $sentence) {
                    $this->quote($article, $sentence, $colors[($position + $i) % count($colors)], $i === 0 ? $note : null);
                }
            }

            $collection->articles()->syncWithoutDetaching([$article->id => ['position' => $position++, 'note' => $note]]);
            $this->line("highlighted {$url}");
        }

        $this->info("Public collection: /c/{$collection->slug}");

        return self::SUCCESS;
    }

    /**
     * The opening definition-style sentences of the article: short enough to read
     * in a card, long enough to say something.
     *
     * @return list<string>
     */
    private function sentences(Article $article): array
    {
        preg_match_all('/[A-Z][^.!?]{60,260}[.!?](?=\s|$)/u', (string) $article->content_text, $matches);

        return array_slice(array_values(array_unique($matches[0])), 0, 2);
    }

    private function quote(Article $article, string $exact, HighlightColor $color, ?string $note): void
    {
        $text = (string) $article->content_text;
        $offset = (int) mb_strpos($text, $exact);

        $article->highlights()->create([
            'exact' => $exact,
            'prefix' => mb_substr(mb_substr($text, 0, $offset), -Highlight::CONTEXT_LENGTH),
            'suffix' => mb_substr($text, $offset + mb_strlen($exact), Highlight::CONTEXT_LENGTH),
            'color' => $color,
            'note' => $note,
            'tags' => $article->tags,
        ]);
    }
}
