import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/landing.css',
                'resources/js/app.js',
                'resources/js/auth.js',
                'resources/js/map.js',
                'resources/js/landing.js',
            ],
            refresh: true,
        }),
    ],
});
