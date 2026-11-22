import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const aliasedRoot = process.env.OQARI_VITE_ROOT;

export default defineConfig({
    root: aliasedRoot || process.cwd(),
    resolve: {
        preserveSymlinks: Boolean(aliasedRoot),
    },
    ...(aliasedRoot && {
        server: {
            watch: { usePolling: true, interval: 250, ignored: ['**/vendor/**', '**/storage/**'] },
        },
    }),
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
