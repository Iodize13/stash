<?php

namespace App\Actions;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * A private, temporary account for "Try the demo": visitors can save links,
 * read and highlight without seeing or touching anyone else's data. It starts
 * with copies of a few already-fetched articles so there is something to read
 * immediately, and is deleted when it expires (see User::prunable()).
 */
class CreateSandbox
{
    /**
     * @return User|null null when the maximum number of live sandboxes is reached
     */
    public function handle(): ?User
    {
        $config = config('stash.sandbox');

        $live = User::whereNotNull('sandbox_expires_at')->where('sandbox_expires_at', '>', now())->count();

        if ($live >= $config['max_active']) {
            return null;
        }

        return DB::transaction(function () use ($config) {
            $tag = Str::upper(Str::random(4));

            $user = User::forceCreate([
                'name' => "Guest {$tag}",
                'email' => 'guest-'.Str::lower((string) Str::uuid()).'@sandbox.invalid',
                'password' => Str::password(32),
                'sandbox_expires_at' => now()->addHours($config['lifetime_hours']),
            ]);
            $user->assignRole(Role::findOrCreate('guest'));

            $this->copySamples($user, $config);

            return $user;
        });
    }

    /**
     * Copies are already "ready", so no fetch job runs; events are muted so
     * the copies do not fill the activity log either.
     *
     * @param  array<string, mixed>  $config
     */
    private function copySamples(User $user, array $config): void
    {
        $template = $config['template_email']
            ? User::where('email', $config['template_email'])->first()
            : null;

        if (! $template) {
            return;
        }

        $samples = $template->articles()
            ->where('status', ArticleStatus::Ready)
            ->whereNotNull('content_html')
            ->with('highlights')
            ->oldest()
            ->limit($config['template_articles'])
            ->get();

        Article::withoutEvents(function () use ($samples, $user) {
            foreach ($samples as $i => $sample) {
                $copy = $sample->replicate(['read_at', 'archived_at'])->fill(['user_id' => $user->id]);
                $copy->save();

                // Leave the rest un-highlighted so there is something to try.
                if ($i < 2) {
                    foreach ($sample->highlights as $highlight) {
                        $copy->highlights()->create($highlight->only(['exact', 'prefix', 'suffix', 'color', 'note', 'tags']));
                    }
                }
            }
        });
    }
}
