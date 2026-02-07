<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Routes that should be excluded from onboarding checks.
     *
     * @var array<string>
     */
    protected array $except = [
        'login',
        'logout',
        'onboarding.*',
        'password.*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $user = Auth::user();
        $settings = $user->settings;

        // If no settings exist, create them (first login)
        if (! $settings) {
            $user->settings()->create([
                'first_login' => true,
            ]);

            return redirect()->route('onboarding.password');
        }

        // Check if password needs to be changed (first login)
        if ($settings->first_login) {
            return redirect()->route('onboarding.password');
        }

        // Backward compatibility: if onboarding is already complete, allow access
        if ($settings->onboarding_completed_at) {
            return $next($request);
        }

        // Route to the correct onboarding step based on progress
        $step = $settings->onboarding_step;

        if ($step === null || $step < 2) {
            return redirect()->route('onboarding.theme');
        }

        if ($step < 3) {
            return redirect()->route('onboarding.workstudio');
        }

        if ($step < 4) {
            return redirect()->route('onboarding.confirmation');
        }

        return $next($request);
    }

    /**
     * Determine if the request should skip onboarding checks.
     */
    protected function shouldSkip(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }
}
