import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
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
            host: '0.0.0.0',
            cors: true,
            hmr: {
                host: env.VITE_HMR_HOST || 'localhost',
                clientPort: env.VITE_HMR_PORT ? Number(env.VITE_HMR_PORT) : undefined,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
