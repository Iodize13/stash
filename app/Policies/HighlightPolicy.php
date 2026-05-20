<?php

namespace App\Policies;

use App\Models\Highlight;
use App\Models\User;

class HighlightPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'demo']);
    }

    public function view(User $user, Highlight $highlight): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Highlight $highlight): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Highlight $highlight): bool
    {
        return $user->hasRole('admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
