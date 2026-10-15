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
        // Bind to IPv4 explicitly. Left to itself the dev server resolved
        // "localhost" to the IPv6 loopback and wrote http://[::1]:5173 into
        // public/hot, so every page served from http://127.0.0.1:8000 tried to
        // fetch its stylesheet over IPv6 and got nothing — the app rendered
        // with no CSS at all. strictPort stops a second instance quietly
        // moving to 5174 and leaving the first one stranded.
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
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
