// Templat vitest.config.ts. Salin ke akar proyek.
import react from '@vitejs/plugin-react';
import path from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  plugins: [react()],
  resolve: { alias: { '@': path.resolve(__dirname, 'resources/js') } },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['resources/js/test/setup.ts'],
    include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
    coverage: { provider: 'v8', include: ['resources/js/features/**', 'resources/js/lib/**'] },
  },
});
