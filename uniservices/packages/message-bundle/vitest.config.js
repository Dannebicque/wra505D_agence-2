import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import path from 'path';

const shared = (dossier) => path.resolve(__dirname, '../../shared', dossier);

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, '../shell/assets'),
      '@components': shared('components'),
      '@config': shared('global-data'),
      '@images': shared('images'),
      '@helpers': shared('helpers'),
      '@requests': shared('requests'),
      '@stores': shared('stores'),
      '@utils': shared('utils'),
      '@composables': shared('composables'),
    },
  },
  test: {
    environment: 'jsdom',
    include: ['assets/**/*.test.js'],
  },
});
