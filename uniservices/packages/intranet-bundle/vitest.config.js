import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import path from 'path';

const shared = (dossier) => path.resolve(__dirname, '../../shared', dossier);

// Les tests de vues montent des composants .vue : il leur faut le plugin et les alias du shell.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'assets'),
      '@components': shared('components'),
      '@config': shared('global-data'),
      '@images': shared('images'),
      '@helpers': shared('helpers'),
      '@requests': shared('requests'),
      '@stores': shared('stores'),
      '@utils': shared('utils'),
      '@composables': shared('composables'),
      '@types': shared('types'),
    },
  },
  test: {
    environment: 'jsdom',
  },
});
