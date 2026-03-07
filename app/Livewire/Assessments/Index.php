<?php

namespace App\Livewire\Assessments;

use App\Models\Assessment;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout.app-shell', ['title' => 'Assessments', 'breadcrumbs' => [['label' => 'Assessments']]])]
class Index extends Component
{
    public ?string $selectedJobGuid = null;

    #[Computed]
    public function assessments(): array
    {
        return Assessment::query()
            ->where('status', 'ACTIV')
            ->with(['metrics', 'contributors'])
            ->whereHas('metrics')
            ->join('assessment_metrics', 'assessments.job_guid', '=', 'assessment_metrics.job_guid')
            ->orderBy('assessment_metrics.oldest_pending_date', 'asc')
            ->select('assessments.*')
            ->get()
            ->toArray();
    }

    #[Computed]
    public function selectedAssessment(): ?array
    {
        if (! $this->selectedJobGuid) {
            return $this->assessments[0] ?? null;
        }

        return collect($this->assessments)->firstWhere('job_guid', $this->selectedJobGuid);
    }

    public function selectAssessment(string $jobGuid): void
    {
        $this->selectedJobGuid = $jobGuid;
        unset($this->selectedAssessment);
    }

    public function render(): View
    {
        return view('livewire.assessments.index');
    }
}
