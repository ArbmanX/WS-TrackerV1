@props([
    'currentStep' => 1,
])

@php
    $steps = App\Enums\OnboardingStep::cases();
    $user = auth()->user();

    // Filter out conditional steps the user doesn't need
    $visibleSteps = collect($steps)->filter(function ($step) use ($user) {
        if (! $step->isConditional()) {
            return true;
        }

        return $user && $user->hasAnyRole($step->requiredRoles());
    })->values();
@endphp

<ul class="steps steps-horizontal w-full text-xs">
    @foreach ($visibleSteps as $step)
        <li class="step {{ $step->value <= $currentStep ? 'step-primary' : '' }}">
            {{ $step->label() }}
        </li>
    @endforeach
</ul>
