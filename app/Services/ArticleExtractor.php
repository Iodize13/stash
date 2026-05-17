<?php

namespace App\Services;

use App\Services\Fetching\FetchedPage;
use App\Services\Fetching\FetchException;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class ArticleExtractor
{
    /**
     * @return array{title: string, byline: ?string, excerpt: string, content_html: string, content_text: string, word_count: int}
     */
    public function extract(FetchedPage $page): array
    {
        $readability = new Readability(
            (new Configuration)
                ->setFixRelativeURLs(true)
                ->setOriginalURL($page->url)
                ->setSummonCthulhu(true)
        );

        try {
            $readability->parse($page->html);
        } catch (ParseException) {
            throw new FetchException('No readable content found');
        }

        // Extracted markup comes from an arbitrary site and is rendered later, so it is
        // sanitized here, once, before it ever reaches the database.
        $html = $this->sanitizer()->sanitize((string) $readability->getContent());
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));

        if ($text === '') {
            throw new FetchException('No readable content found');
        }

        $title = trim((string) $readability->getTitle()) ?: (string) parse_url($page->url, PHP_URL_HOST);
        $excerpt = trim((string) $readability->getExcerpt()) ?: Str::limit($text, 280);

        return [
            'title' => Str::limit($title, 250),
            'byline' => Str::limit(trim((string) $readability->getAuthor()), 250) ?: null,
            'excerpt' => Str::limit(strip_tags($excerpt), 500),
            'content_html' => $html,
            'content_text' => $text,
            'word_count' => count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY)),
        ];
    }

    private function sanitizer(): HtmlSanitizer
    {
        return new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowMediaSchemes(['https'])
                ->allowRelativeLinks(false)
                ->allowRelativeMedias(false)
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->forceAttribute('a', 'target', '_blank')
                ->forceAttribute('img', 'loading', 'lazy')
                ->forceAttribute('img', 'referrerpolicy', 'no-referrer')
                ->withMaxInputLength(Fetching\SafeHttpFetcher::MAX_BYTES)
        );
    }
}
