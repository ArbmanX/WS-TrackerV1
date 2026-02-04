{{--
    User Menu Component

    DaisyUI dropdown menu showing:
    - User avatar with initials
    - User name and email
    - Settings link
    - Logout button

    Usage:
    <x-layout.user-menu />
--}}

@auth
    <div class="dropdown dropdown-end" x-data="{ open: false }">
        {{-- Avatar Button --}}
        <button
            type="button"
            tabindex="0"
            @click="open = !open"
            @click.outside="open = false"
            class="btn btn-ghost btn-circle avatar placeholder"
            data-test="user-menu-button"
        >
            <div class="w-10 rounded-full bg-neutral text-neutral-content">
                <span class="text-sm font-medium">
                    {{ auth()->user()->initials() }}
                </span>
            </div>
        </button>

        {{-- Dropdown Content --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="dropdown-content menu z-50 mt-3 w-64 rounded-box bg-base-200 p-2 shadow-lg"
            tabindex="0"
        >
            {{-- User Info Section --}}
            <div class="flex items-center gap-3 px-3 py-2">
                <div class="avatar placeholder">
                    <div class="w-12 rounded-full bg-neutral text-neutral-content">
                        <span class="text-lg font-medium">
                            {{ auth()->user()->initials() }}
                        </span>
                    </div>
                </div>
                <div class="flex-1 overflow-hidden">
                    <p class="font-semibold truncate">
                        {{ auth()->user()->name }}
                    </p>
                    <p class="text-sm text-base-content/70 truncate">
                        {{ auth()->user()->email }}
                    </p>
                </div>
            </div>

            <div class="divider my-1"></div>

            {{-- Menu Items --}}
            <ul class="menu menu-sm">
                {{-- Settings --}}
                <li>
                    <a
                        href="/settings"
                        wire:navigate
                        @click="open = false"
                        class="flex items-center gap-3"
                    >
                        <x-ui.icon name="cog-6-tooth" size="sm" />
                        <span>{{ __('Settings') }}</span>
                    </a>
                </li>

                <div class="divider my-1"></div>

                {{-- Logout --}}
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="flex w-full items-center gap-3 text-error"
                            data-test="logout-button"
                        >
                            <x-ui.icon name="arrow-right-start-on-rectangle" size="sm" />
                            <span>{{ __('Log Out') }}</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
@else
    {{-- Guest: Show login button --}}
    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">
        {{ __('Log In') }}
    </a>
@endauth
