<?php

namespace App\Enums;

enum OnboardingStep: int
{
    case Password = 1;
    case Theme = 2;
    case Credentials = 3;
    case TeamSelection = 4;
    case HomePage = 5;
    case Confirmation = 6;

    /**
     * Human-readable label for this step.
     */
    public function label(): string
    {
        return match ($this) {
            self::Password => 'Password',
            self::Theme => 'Theme',
            self::Credentials => 'Credentials',
            self::TeamSelection => 'Teams',
            self::HomePage => 'Home Page',
            self::Confirmation => 'Confirm',
        };
    }

    /**
     * Named route for this step.
     */
    public function route(): string
    {
        return match ($this) {
            self::Password => 'onboarding.password',
            self::Theme => 'onboarding.theme',
            self::Credentials => 'onboarding.workstudio',
            self::TeamSelection => 'onboarding.team-selection',
            self::HomePage => 'onboarding.home-page',
            self::Confirmation => 'onboarding.confirmation',
        };
    }

    /**
     * Whether this step is conditional (only shown to certain roles).
     */
    public function isConditional(): bool
    {
        return match ($this) {
            self::TeamSelection => true,
            default => false,
        };
    }

    /**
     * Roles required for conditional steps.
     */
    public function requiredRoles(): array
    {
        return match ($this) {
            self::TeamSelection => ['general-foreman', 'manager'],
            default => [],
        };
    }
}
