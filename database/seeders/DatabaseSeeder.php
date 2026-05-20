<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Collection;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Refusing to seed in production.');

            return;
        }

        $accounts = [
            ['admin', 'Admin', 'admin@example.test', config('seed.admin_password')],
            ['demo', 'Demo', 'demo@example.test', config('seed.demo_password')],
        ];

        foreach ($accounts as [$role, $name, $email, $password]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password],
            );

            $user->syncRoles(Role::findOrCreate($role));

            if ($role === 'admin' && ! $user->articles()->exists()) {
                $this->seedArticles($user);
            }
        }
    }

    /**
     * Sample data only: no fetch jobs are queued for these made-up links.
     */
    private function seedArticles(User $owner): void
    {
        Article::withoutEvents(function () use ($owner) {
            $highlighted = Article::factory()->count(22)->for($owner)->create()
                ->take(8)
                ->each(fn (Article $article) => collect(range(0, fake()->numberBetween(1, 4)))
                    ->each(fn (int $n) => Highlight::factory()->quoting($article, $n * 4 + 1)->create()));

            $collection = Collection::factory()->for($owner)->public()->create([
                'title' => 'Systems reading list',
                'slug' => 'systems-reading',
                'description' => 'Papers and essays on storage engines, networking and distributed systems, with the passages worth remembering.',
            ]);
            $highlighted->take(4)->values()->each(fn (Article $article, int $i) => $collection->articles()->attach($article, [
                'position' => $i,
                'note' => $i % 2 === 0 ? fake()->sentence(16) : null,
            ]));
            Article::factory()->count(6)->for($owner)->read()->archived()->create();
            Article::factory()->count(3)->for($owner)->queued()->create();
            Article::factory()->for($owner)->fetching()->create();
            Article::factory()->for($owner)->failed()->create();
        });
    }
}
