<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, MassPrunable, Notifiable;

    /** @return HasMany<Article, $this> */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function isSandbox(): bool
    {
        return $this->sandbox_expires_at !== null;
    }

    /**
     * Expired sandbox accounts, removed by the scheduled `model:prune`. Their
     * articles, highlights and collection rows go with them via cascading keys.
     *
     * @return Builder<User>
     */
    public function prunable(): Builder
    {
        return static::query()->whereNotNull('sandbox_expires_at')->where('sandbox_expires_at', '<', now());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'demo']);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'sandbox_expires_at' => 'datetime',
        ];
    }
}
