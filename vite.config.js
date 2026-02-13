import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/cookie_banner.css',
                'resources/css/parent.css',
                'resources/js/cookie_dialog.js',

                'resources/js/cp.js',
                'resources/css/cp.css'
            ],
            publicDirectory: 'resources/dist',
        }),
        statamic(),
        tailwindcss(),
    ],

    build: {
        rollupOptions: {
            output: {
                entryFileNames: `[name].js`,
                chunkFileNames: `[name].js`,
                assetFileNames: `[name].[ext]`
            }
        }
    }
});
