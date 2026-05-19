<?php

namespace App\Models;

use App\Enums\HighlightColor;
use Database\Factories\HighlightFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['article_id', 'exact', 'prefix', 'suffix', 'color', 'note', 'tags'])]
class Highlight extends Model
{
    /** @use HasFactory<HighlightFactory> */
    use HasFactory;

    public const CONTEXT_LENGTH = 32;

    public const MAX_LENGTH = 2000;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => HighlightColor::class,
            'tags' => 'array',
        ];
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * What the reader script needs to re-anchor the highlight in the page.
     *
     * @return array{id: int, exact: string, prefix: string, suffix: string, color: string}
     */
    public function anchor(): array
    {
        return [
            'id' => $this->id,
            'exact' => $this->exact,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'color' => $this->color->value,
        ];
    }
}
