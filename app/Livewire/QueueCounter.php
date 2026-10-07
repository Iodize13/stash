<?php

namespace App\Livewire;

use App\Models\Article;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class QueueCounter extends Component
{
    public function render(): View
    {
        return view('livewire.queue-counter', ['count' => Article::active()->whereBelongsTo(auth()->user())->count()]);
    }
}
