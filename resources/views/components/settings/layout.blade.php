<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <ul class="menu bg-base-100 rounded-box w-full p-0">
            <li><a href="{{ route('profile.edit') }}" wire:navigate class="{{ request()->routeIs('profile.edit') ? 'active' : '' }}">{{ __('Profile') }}</a></li>
            <li><a href="{{ route('user-password.edit') }}" wire:navigate class="{{ request()->routeIs('user-password.edit') ? 'active' : '' }}">{{ __('Password') }}</a></li>
            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <li><a href="{{ route('two-factor.show') }}" wire:navigate class="{{ request()->routeIs('two-factor.show') ? 'active' : '' }}">{{ __('Two-Factor Auth') }}</a></li>
            @endif
            <li><a href="{{ route('appearance.edit') }}" wire:navigate class="{{ request()->routeIs('appearance.edit') ? 'active' : '' }}">{{ __('Appearance') }}</a></li>
        </ul>
    </div>

    <div class="divider md:hidden"></div>

    <div class="flex-1 self-stretch max-md:pt-6">
        <h2 class="text-lg font-semibold text-base-content">{{ $heading ?? '' }}</h2>
        <p class="text-sm text-base-content/70">{{ $subheading ?? '' }}</p>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
