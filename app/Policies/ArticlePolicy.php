<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

/**
 * admin: the owner/operator, can do everything.
 * demo:  read-only viewer of everything.
 * guest: a temporary sandbox account, full control of its own articles only.
 */
class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'demo', 'guest']);
    }

    public function view(User $user, Article $article): bool
    {
        return $user->hasAnyRole(['admin', 'demo']) || $this->owns($user, $article);
    }

    /** Also used as "may manage their own library" (bulk actions, saving links). */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'guest']);
    }

    public function update(User $user, Article $article): bool
    {
        return $user->hasRole('admin') || $this->owns($user, $article);
    }

    public function delete(User $user, Article $article): bool
    {
        return $this->update($user, $article);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    private function owns(User $user, Article $article): bool
    {
        return $user->hasRole('guest') && $article->user_id === $user->id;
    }
}
