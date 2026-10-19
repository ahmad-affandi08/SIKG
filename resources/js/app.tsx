import { createInertiaApp } from '@inertiajs/react';
import { QueryClientProvider } from '@tanstack/react-query';
import type { ComponentType } from 'react';

import { BRAND } from '@/lib/brand';
import { registerServiceWorker } from '@/lib/offline/register';
import { createQueryClient } from '@/lib/queryClient';
import { installZodIndonesian } from '@/lib/zod-id';

import '../css/app.css';

installZodIndonesian();
registerServiceWorker();

const queryClient = createQueryClient();
const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx');

void createInertiaApp({
  // CSP `style-src 'self'` menolak <style> yang disisipkan pustaka progres; gayanya ada di app.css (includeCSS: false).
  progress: { includeCSS: false, showSpinner: false },
  title: (title) => (title ? `${title} - ${BRAND.name}` : BRAND.name),
  resolve: async (name) => {
    const loader = pages[`./Pages/${name}.tsx`];
    if (!loader) {
      throw new Error(`Halaman Inertia tidak ditemukan: ${name}`);
    }
    const module = await loader();
    return module.default;
  },
  withApp: (app) => <QueryClientProvider client={queryClient}>{app}</QueryClientProvider>,
});
