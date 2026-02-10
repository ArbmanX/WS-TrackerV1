<?php

namespace App\Livewire\PlannerAnalytics;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class MockPreview extends Component
{
    #[Url]
    public string $design = 'a';

    public function render(): \Illuminate\Contracts\View\View
    {
        $view = match ($this->design) {
            'b' => 'livewire.planner-analytics.mock-b',
            default => 'livewire.planner-analytics.mock-a',
        };

        return view($view);
    }
}
