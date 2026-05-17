<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'demo']);
    }

    public function view(User $user, Article $article): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Article $article): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->hasRole('admin');
    }
}
