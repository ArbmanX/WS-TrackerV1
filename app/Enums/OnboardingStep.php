<?php

namespace App\Enums;

enum OnboardingStep: int
{
    case Password = 1;
    case Theme = 2;
    case Credentials = 3;
    case Confirmation = 4;

    /**
     * Human-readable label for this step.
     */
    public function label(): string
    {
        return match ($this) {
            self::Password => 'Password',
            self::Theme => 'Theme',
            self::Credentials => 'Credentials',
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
            self::Confirmation => 'onboarding.confirmation',
        };
    }
}
