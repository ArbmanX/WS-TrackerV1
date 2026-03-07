@props([
    'currentStep' => 1,
])

@php
    $steps = [
        1 => 'Select Credentials',
        2 => 'Verify Info',
        3 => 'Assign Role',
        4 => 'Regions & Assessments',
        5 => 'Review & Save',
    ];
@endphp

<ul class="steps steps-horizontal w-full text-xs">
    @foreach ($steps as $num => $label)
        <li
            @if($num < $currentStep)
                wire:click="goToStep({{ $num }})"
            @endif
            class="step {{ $num <= $currentStep ? 'step-primary' : '' }} {{ $num < $currentStep ? 'cursor-pointer' : '' }}"
        >
            {{ $label }}
        </li>
    @endforeach
</ul>
