<?php

namespace App\Livewire;

use Livewire\Component;

class Smoke extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.smoke');
    }
}
