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
            'themes' => ['cupcake', 'bumblebee', 'emerald', 'retro', 'valentine', 'garden', 'aqua', 'lofi', 'pastel', 'fantasy', 'wireframe', 'cmyk', 'autumn', 'acid', 'lemonade', 'winter', 'nord', 'caramellatte', 'silk'],
        ],
        'dark' => [
            'label' => 'Dark Themes',
            'themes' => ['synthwave', 'cyberpunk', 'halloween', 'forest', 'black', 'luxury', 'dracula', 'business', 'night', 'coffee', 'dim', 'sunset', 'abyss'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Themes
    |--------------------------------------------------------------------------
    |
    | All available DaisyUI 5 themes with metadata for the picker.
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
        'bumblebee' => [
            'name' => 'Bumblebee',
            'colorScheme' => 'light',
            'description' => 'Yellow and black',
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
        'valentine' => [
            'name' => 'Valentine',
            'colorScheme' => 'light',
            'description' => 'Pink and romantic',
        ],
        'garden' => [
            'name' => 'Garden',
            'colorScheme' => 'light',
            'description' => 'Natural greens',
        ],
        'aqua' => [
            'name' => 'Aqua',
            'colorScheme' => 'light',
            'description' => 'Ocean blues',
        ],
        'lofi' => [
            'name' => 'Lofi',
            'colorScheme' => 'light',
            'description' => 'Minimal monochrome',
        ],
        'pastel' => [
            'name' => 'Pastel',
            'colorScheme' => 'light',
            'description' => 'Soft pastels',
        ],
        'fantasy' => [
            'name' => 'Fantasy',
            'colorScheme' => 'light',
            'description' => 'Purple magic',
        ],
        'wireframe' => [
            'name' => 'Wireframe',
            'colorScheme' => 'light',
            'description' => 'Sketch-like wireframe',
        ],
        'cmyk' => [
            'name' => 'CMYK',
            'colorScheme' => 'light',
            'description' => 'Print color model',
        ],
        'autumn' => [
            'name' => 'Autumn',
            'colorScheme' => 'light',
            'description' => 'Warm earth tones',
        ],
        'acid' => [
            'name' => 'Acid',
            'colorScheme' => 'light',
            'description' => 'Bright neon',
        ],
        'lemonade' => [
            'name' => 'Lemonade',
            'colorScheme' => 'light',
            'description' => 'Fresh citrus',
        ],
        'winter' => [
            'name' => 'Winter',
            'colorScheme' => 'light',
            'description' => 'Cool blues',
        ],
        'nord' => [
            'name' => 'Nord',
            'colorScheme' => 'light',
            'description' => 'Arctic minimalism',
        ],
        'caramellatte' => [
            'name' => 'Caramel Latte',
            'colorScheme' => 'light',
            'description' => 'Warm caramel tones',
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
        'halloween' => [
            'name' => 'Halloween',
            'colorScheme' => 'dark',
            'description' => 'Spooky orange',
        ],
        'forest' => [
            'name' => 'Forest',
            'colorScheme' => 'dark',
            'description' => 'Dark greens',
        ],
        'black' => [
            'name' => 'Black',
            'colorScheme' => 'dark',
            'description' => 'Pure black OLED',
        ],
        'luxury' => [
            'name' => 'Luxury',
            'colorScheme' => 'dark',
            'description' => 'Gold and dark',
        ],
        'dracula' => [
            'name' => 'Dracula',
            'colorScheme' => 'dark',
            'description' => 'Popular dark scheme',
        ],
        'business' => [
            'name' => 'Business',
            'colorScheme' => 'dark',
            'description' => 'Professional dark',
        ],
        'night' => [
            'name' => 'Night',
            'colorScheme' => 'dark',
            'description' => 'Deep dark blue',
        ],
        'coffee' => [
            'name' => 'Coffee',
            'colorScheme' => 'dark',
            'description' => 'Warm browns',
        ],
        'dim' => [
            'name' => 'Dim',
            'colorScheme' => 'dark',
            'description' => 'Muted and subtle',
        ],
        'sunset' => [
            'name' => 'Sunset',
            'colorScheme' => 'dark',
            'description' => 'Warm sunset hues',
        ],
        'abyss' => [
            'name' => 'Abyss',
            'colorScheme' => 'dark',
            'description' => 'Deep ocean dark',
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
