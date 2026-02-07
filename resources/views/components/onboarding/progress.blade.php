@props([
    'currentStep' => 1,
    'totalSteps' => 4,
])

@php
    $steps = App\Enums\OnboardingStep::cases();
@endphp

<ul class="steps steps-horizontal w-full text-xs">
    @foreach ($steps as $step)
        <li class="step {{ $step->value <= $currentStep ? 'step-primary' : '' }}">
            {{ $step->label() }}
        </li>
    @endforeach
</ul>
