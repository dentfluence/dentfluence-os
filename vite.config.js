import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/communication/module.css',
                'resources/css/communication/prm.css',
                'resources/css/communication/manager.css',
                'resources/css/communication/followup.css',
                'resources/css/communication/huddle.css',
                'resources/css/communication/opportunities.css',
                'resources/js/communication/navigation.js',
                'resources/js/communication/followup-calendar.js',
                'resources/js/communication/followup-modals.js',
                'resources/js/communication/opportunities.js',
                'resources/js/communication/huddle.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});