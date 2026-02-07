<?php

use App\Enums\OnboardingStep;

it('has the correct integer values', function () {
    expect(OnboardingStep::Password->value)->toBe(1);
    expect(OnboardingStep::Theme->value)->toBe(2);
    expect(OnboardingStep::Credentials->value)->toBe(3);
    expect(OnboardingStep::Confirmation->value)->toBe(4);
});

it('returns correct labels', function () {
    expect(OnboardingStep::Password->label())->toBe('Password');
    expect(OnboardingStep::Theme->label())->toBe('Theme');
    expect(OnboardingStep::Credentials->label())->toBe('Credentials');
    expect(OnboardingStep::Confirmation->label())->toBe('Confirm');
});

it('returns correct route names', function () {
    expect(OnboardingStep::Password->route())->toBe('onboarding.password');
    expect(OnboardingStep::Theme->route())->toBe('onboarding.theme');
    expect(OnboardingStep::Credentials->route())->toBe('onboarding.workstudio');
    expect(OnboardingStep::Confirmation->route())->toBe('onboarding.confirmation');
});

it('has exactly 4 cases', function () {
    expect(OnboardingStep::cases())->toHaveCount(4);
});

it('can be created from integer value', function () {
    expect(OnboardingStep::from(1))->toBe(OnboardingStep::Password);
    expect(OnboardingStep::from(2))->toBe(OnboardingStep::Theme);
    expect(OnboardingStep::from(3))->toBe(OnboardingStep::Credentials);
    expect(OnboardingStep::from(4))->toBe(OnboardingStep::Confirmation);
});
