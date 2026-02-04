/**
 * Alpine.js State Management Stores
 *
 * Global stores for application state shared across all components.
 * Uses stores (not data) so state is shared and persists across
 * Livewire SPA navigation.
 *
 * Stores:
 * - theme: Theme preference with localStorage + system detection
 * - sidebar: Sidebar open/collapsed state per breakpoint
 *
 * Persistence:
 * - localStorage: Immediate cache for fast page loads (no FOUC)
 */

/**
 * Theme Store
 *
 * Manages DaisyUI theme with:
 * - localStorage persistence for instant load (no flash)
 * - System preference detection and reactivity
 * - Livewire SPA navigation compatibility
 */
export function registerThemeStore(Alpine) {
    Alpine.store('theme', {
        // Current theme setting ('system', 'corporate', 'dark', etc.)
        current: localStorage.getItem('ws-theme') || 'corporate',

        // Detected system preference
        systemPreference: 'light',

        // Theme metadata from config (populated by Blade via theme-toggle)
        availableThemes: {},

        init() {
            // Detect system preference
            this.detectSystemPreference();

            // Listen for system preference changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                this.systemPreference = e.matches ? 'dark' : 'light';
                if (this.current === 'system') {
                    this.applyTheme();
                }
            });

            // Apply theme immediately
            this.applyTheme();

            // Re-apply theme during Livewire SPA navigation
            // Critical because wire:navigate keeps the <html> element
            // but the FOUC prevention script doesn't re-run
            document.addEventListener('livewire:navigating', () => {
                this.applyTheme();
            });

            document.addEventListener('livewire:navigated', () => {
                this.applyTheme();
            });
        },

        detectSystemPreference() {
            this.systemPreference = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        },

        /**
         * Get the effective theme (resolved 'system' to actual theme)
         */
        get effective() {
            if (this.current === 'system') {
                // Map system preference to theme
                const mapping = {
                    'light': 'corporate',
                    'dark': 'dark',
                };
                return mapping[this.systemPreference] || 'corporate';
            }
            return this.current;
        },

        /**
         * Check if current effective theme is dark
         */
        get isDark() {
            const darkThemes = ['dark', 'synthwave', 'cyberpunk', 'dracula', 'night', 'forest', 'coffee'];
            return darkThemes.includes(this.effective);
        },

        /**
         * Get display name for current theme
         */
        get currentName() {
            if (this.current === 'system') {
                return 'System';
            }
            return this.availableThemes[this.current]?.name || this.current;
        },

        /**
         * Set theme and persist
         */
        set(themeName) {
            this.current = themeName;
            localStorage.setItem('ws-theme', themeName);
            this.applyTheme();

            // Dispatch event for charts and other components that need to re-render
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: themeName } }));
        },

        /**
         * Apply theme to document
         */
        applyTheme() {
            document.documentElement.setAttribute('data-theme', this.effective);
        },
    });
}


/**
 * Sidebar Store
 *
 * Manages sidebar visibility across breakpoints:
 * - Mobile (<768px): Drawer overlay, closed by default
 * - Tablet (768-1024px): Collapsed to icons, expandable on hover
 * - Desktop (>1024px): Expanded by default, collapsible
 */
export function registerSidebarStore(Alpine) {
    Alpine.store('sidebar', {
        // Is the sidebar drawer open? (mobile)
        isOpen: false,

        // Is the sidebar collapsed to icons? (tablet/desktop)
        isCollapsed: localStorage.getItem('ws-sidebar-collapsed') === 'true',

        // Is user hovering over collapsed sidebar?
        isHovering: false,

        // Current breakpoint
        breakpoint: 'desktop',

        init() {
            this.detectBreakpoint();
            window.addEventListener('resize', () => this.detectBreakpoint());
        },

        detectBreakpoint() {
            const width = window.innerWidth;
            if (width < 768) {
                this.breakpoint = 'mobile';
                this.isOpen = false; // Close drawer on resize to mobile
            } else if (width < 1024) {
                this.breakpoint = 'tablet';
                this.isCollapsed = true; // Always collapsed on tablet
            } else {
                this.breakpoint = 'desktop';
                // Restore user preference on desktop
                this.isCollapsed = localStorage.getItem('ws-sidebar-collapsed') === 'true';
            }
        },

        /**
         * Toggle drawer (mobile) or collapse (desktop)
         */
        toggle() {
            if (this.breakpoint === 'mobile') {
                this.isOpen = !this.isOpen;
            } else {
                this.toggleCollapse();
            }
        },

        /**
         * Open drawer (mobile)
         */
        open() {
            this.isOpen = true;
        },

        /**
         * Close drawer (mobile)
         */
        close() {
            this.isOpen = false;
        },

        /**
         * Toggle collapsed state (tablet/desktop)
         */
        toggleCollapse() {
            this.isCollapsed = !this.isCollapsed;
            // Only persist on desktop (tablet is always collapsed)
            if (this.breakpoint === 'desktop') {
                localStorage.setItem('ws-sidebar-collapsed', this.isCollapsed);
            }
        },

        /**
         * Handle hover enter (expand collapsed sidebar temporarily)
         */
        hoverEnter() {
            if (this.isCollapsed) {
                this.isHovering = true;
            }
        },

        /**
         * Handle hover leave
         */
        hoverLeave() {
            this.isHovering = false;
        },

        /**
         * Get current sidebar width class
         */
        get widthClass() {
            if (this.breakpoint === 'mobile') {
                return 'w-72';
            }
            if (this.isCollapsed && !this.isHovering) {
                return 'w-16';
            }
            return 'w-64';
        },

        /**
         * Should show full content (labels, not just icons)?
         */
        get showLabels() {
            if (this.breakpoint === 'mobile') {
                return true;
            }
            return !this.isCollapsed || this.isHovering;
        },
    });
}


/**
 * Register all stores
 */
export function registerAllStores(Alpine) {
    registerThemeStore(Alpine);
    registerSidebarStore(Alpine);
}

// Auto-register when Alpine is ready
document.addEventListener('alpine:init', () => {
    registerAllStores(Alpine);
});
