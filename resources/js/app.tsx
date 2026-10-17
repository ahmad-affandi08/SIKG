import { createInertiaApp } from '@inertiajs/react';
import { QueryClientProvider } from '@tanstack/react-query';
import type { ComponentType } from 'react';

import { createQueryClient } from '@/lib/queryClient';
import { installZodIndonesian } from '@/lib/zod-id';

import '../css/app.css';

installZodIndonesian();

const queryClient = createQueryClient();
const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx');

void createInertiaApp({
  title: (title) => (title ? `${title} - SIKG` : 'SIKG'),
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
