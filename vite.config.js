import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            // Laravel rewrites compiled Blade views and cache files constantly,
            // and on Windows the watcher can grab a .tmp file mid-rename and die
            // with EBUSY. None of it is source, so none of it needs watching.
            ignored: [
                '**/storage/**',
                '**/vendor/**',
                '**/bootstrap/cache/**',
                '**/public/build/**',
            ],
        },
    },
});
