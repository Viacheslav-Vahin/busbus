// tailwind.config.js
import preset from './vendor/filament/support/tailwind.config.preset'

import preset from './vendor/filament/support/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './resources/js/**/*.{js,jsx,ts,tsx}',
    ],
    theme: {
        extend: {
            colors: {
                // Основні кольори бренду
                brand: {
                    light: '#faa51a',
                    DEFAULT: '#faa51a',
                    dark:  '#2c2a29'
                },
            },
        },
    },
}

