import { QueryClient } from '@tanstack/react-query';

import { HttpError } from '@/lib/http';

const THIRTY_SECONDS = 30_000;
const TEN_MINUTES = 600_000;

/** Waktu basi bawaan 30 detik; data master 10 menit (docs/PRD/07-frontend.md). Cache query tidak di-persist (M11). */
export const STALE_TIME = { default: THIRTY_SECONDS, master: TEN_MINUTES } as const;

export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: STALE_TIME.default,
        gcTime: STALE_TIME.master,
        refetchOnWindowFocus: false,
        retry: (failureCount, error) => {
          if (error instanceof HttpError && error.status >= 400 && error.status < 500) {
            return false;
          }
          return failureCount < 2;
        },
      },
      mutations: { retry: false },
    },
  });
}
