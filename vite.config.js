import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/marketing.css', 'resources/js/app.js', 'resources/js/marketing.js', 'resources/js/docs.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: { host: '0.0.0.0', port: 5173, hmr: { host: 'localhost' } },
});
