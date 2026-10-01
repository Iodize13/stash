<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('API tokens')]
class ApiTokens extends Component
{
    public const ABILITIES = [
        'articles:read' => 'Read articles',
        'articles:write' => 'Save links',
    ];

    public string $name = '';

    /** @var list<string> */
    public array $abilities = ['articles:read', 'articles:write'];

    /** Shown once, right after creation; never stored in plain text. */
    #[Locked]
    public ?string $plainTextToken = null;

    public function create(): void
    {
        $this->authorize('manage-tokens');

        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(array_keys(self::ABILITIES))],
        ]);

        $this->plainTextToken = auth()->user()
            ->createToken($this->name, array_values(array_unique($this->abilities)))
            ->plainTextToken;

        $this->reset('name');
    }

    public function revoke(int $id): void
    {
        $this->authorize('manage-tokens');

        auth()->user()->tokens()->whereKey($id)->delete();
    }

    public function dismissToken(): void
    {
        $this->plainTextToken = null;
    }

    public function render(): View
    {
        return view('livewire.api-tokens', [
            'tokens' => auth()->user()->tokens()->latest()->get(),
            'canManage' => auth()->user()->can('manage-tokens'),
        ]);
    }
}
