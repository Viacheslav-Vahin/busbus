import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/index.tsx',
            ],
            refresh: true,
        }),
        react(),
    ],
    // plugins: [
    //     laravel({
    //         input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/app.tsx'],
    //         refresh: true,
    //     }),
    //     react(),
    // ],
    // server: {
    //     proxy: {
    //         // all calls to /api will be forwarded to Laravel, який слухає на http://127.0.0.1:8000
    //         '/api': {
    //             target: 'http://127.0.0.1:8000',
    //             changeOrigin: true,
    //             secure: false,
    //         },
    //     },
    // },
});
