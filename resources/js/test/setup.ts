import '@testing-library/jest-dom/vitest';

import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

import { installZodIndonesian } from '@/lib/zod-id';

installZodIndonesian();

afterEach(() => {
  cleanup();
});
