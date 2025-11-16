import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        // Disable source maps in production for security and smaller bundle size
        // Keep them enabled in development for easier debugging
        sourcemap: mode !== 'production'
    }
}));
