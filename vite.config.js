import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    base: './',
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/scss/admin.scss', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
