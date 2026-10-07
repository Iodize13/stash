<?php

namespace App\Policies;

use App\Models\Highlight;
use App\Models\User;

/** Same roles as ArticlePolicy; a highlight belongs to whoever owns its article. */
class HighlightPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'demo', 'guest']);
    }

    public function view(User $user, Highlight $highlight): bool
    {
        return $user->hasAnyRole(['admin', 'demo']) || $this->owns($user, $highlight);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'guest']);
    }

    public function update(User $user, Highlight $highlight): bool
    {
        return $user->hasRole('admin') || $this->owns($user, $highlight);
    }

    public function delete(User $user, Highlight $highlight): bool
    {
        return $this->update($user, $highlight);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    private function owns(User $user, Highlight $highlight): bool
    {
        return $user->hasRole('guest') && $highlight->article()->value('user_id') === $user->id;
    }
}
