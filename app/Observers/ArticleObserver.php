<?php

namespace App\Observers;

use App\Enums\ArticleStatus;
use App\Jobs\FetchArticle;
use App\Models\Article;

class ArticleObserver
{
    public function created(Article $article): void
    {
        if ($article->status === ArticleStatus::Queued) {
            FetchArticle::dispatch($article)->afterCommit();
        }
    }
}
