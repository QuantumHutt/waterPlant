// Netlify build of the ERP: a Vue SPA (spa/) backed by Netlify Functions + Netlify Database.
// The original Laravel build still uses vite.config.js.
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    root: path.resolve(__dirname, 'spa'),
    publicDir: path.resolve(__dirname, 'spa/public'),
    plugins: [tailwindcss(), vue()],
    resolve: {
        alias: { '@': path.resolve(__dirname, 'spa/src') },
    },
    build: {
        outDir: path.resolve(__dirname, 'dist'),
        emptyOutDir: true,
    },
});
