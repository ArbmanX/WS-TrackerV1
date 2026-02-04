<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Theme
    |--------------------------------------------------------------------------
    |
    | The default theme for new users and as a fallback if the user's
    | theme preference is invalid.
    |
    */
    'default' => 'corporate',

    /*
    |--------------------------------------------------------------------------
    | Theme Categories
    |--------------------------------------------------------------------------
    |
    | Organize themes into categories for the theme picker UI.
    | 'featured' themes appear first/prominently in the picker.
    |
    */
    'categories' => [
        'featured' => [
            'label' => 'Recommended',
            'themes' => ['corporate', 'light', 'dark'],
        ],
        'light' => [
            'label' => 'Light Themes',
            'themes' => ['cupcake', 'emerald', 'retro', 'garden', 'winter', 'autumn', 'silk'],
        ],
        'dark' => [
            'label' => 'Dark Themes',
            'themes' => ['synthwave', 'cyberpunk', 'dracula', 'night', 'forest', 'coffee'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Themes
    |--------------------------------------------------------------------------
    |
    | All available DaisyUI themes with metadata for the picker.
    | 'colorScheme' determines if theme is light or dark (for system preference).
    |
    */
    'available' => [
        // Featured / Default
        'corporate' => [
            'name' => 'Corporate',
            'colorScheme' => 'light',
            'description' => 'Professional and clean',
        ],
        'light' => [
            'name' => 'Light',
            'colorScheme' => 'light',
            'description' => 'Default light theme',
        ],
        'dark' => [
            'name' => 'Dark',
            'colorScheme' => 'dark',
            'description' => 'Default dark theme',
        ],

        // Light Themes
        'cupcake' => [
            'name' => 'Cupcake',
            'colorScheme' => 'light',
            'description' => 'Soft and pastel',
        ],
        'emerald' => [
            'name' => 'Emerald',
            'colorScheme' => 'light',
            'description' => 'Green accents',
        ],
        'retro' => [
            'name' => 'Retro',
            'colorScheme' => 'light',
            'description' => 'Vintage vibes',
        ],
        'garden' => [
            'name' => 'Garden',
            'colorScheme' => 'light',
            'description' => 'Natural greens',
        ],
        'winter' => [
            'name' => 'Winter',
            'colorScheme' => 'light',
            'description' => 'Cool blues',
        ],
        'autumn' => [
            'name' => 'Autumn',
            'colorScheme' => 'light',
            'description' => 'Warm earth tones',
        ],
        'silk' => [
            'name' => 'Silk',
            'colorScheme' => 'light',
            'description' => 'Elegant and smooth',
        ],

        // Dark Themes
        'synthwave' => [
            'name' => 'Synthwave',
            'colorScheme' => 'dark',
            'description' => '80s neon aesthetic',
        ],
        'cyberpunk' => [
            'name' => 'Cyberpunk',
            'colorScheme' => 'dark',
            'description' => 'Futuristic yellow',
        ],
        'dracula' => [
            'name' => 'Dracula',
            'colorScheme' => 'dark',
            'description' => 'Popular dark scheme',
        ],
        'night' => [
            'name' => 'Night',
            'colorScheme' => 'dark',
            'description' => 'Deep dark blue',
        ],
        'forest' => [
            'name' => 'Forest',
            'colorScheme' => 'dark',
            'description' => 'Dark greens',
        ],
        'coffee' => [
            'name' => 'Coffee',
            'colorScheme' => 'dark',
            'description' => 'Warm browns',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | System Theme Mapping
    |--------------------------------------------------------------------------
    |
    | When user selects "Follow system", map to these themes based on
    | their OS preference (light/dark mode).
    |
    */
    'system_mapping' => [
        'light' => 'corporate',
        'dark' => 'dark',
    ],
];
