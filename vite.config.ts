import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';

export default defineConfig(({ mode }) => ({
	plugins: [sveltekit()],
	build: {
		// Disable source maps in production for security and smaller bundle size
		// Keep them enabled in development for easier debugging
		sourcemap: mode !== 'production'
	}
}));
