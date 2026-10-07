<?php

use App\Models\User;
use Illuminate\Support\Facades\Schedule;

// Delete expired "Try the demo" sandboxes (and, by cascade, their articles and highlights).
Schedule::command('model:prune', ['--model' => [User::class]])->hourly();

// Keep the article history from growing forever.
Schedule::command('activitylog:clean', ['--days' => 90])->daily();
