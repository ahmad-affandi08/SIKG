import { Link } from '@inertiajs/react';

import { useOfflineStatus } from '@/lib/offline/useOfflineStatus';

/** Penanda koneksi dan antrean offline di bilah status; tampil hanya bila offline atau ada antrean. */
export function OfflineIndicator() {
  const status = useOfflineStatus();
  if (status.online && status.pending === 0 && status.flagged === 0 && status.notice === null) {
    return null;
  }
  return (
    <span className="flex items-center gap-2" role="status">
      {!status.online ? <strong>Offline</strong> : null}
      {status.pending > 0 ? (
        <Link href="/offline/queue" className="underline">
          {String(status.pending)} menunggu dikirim
        </Link>
      ) : null}
      {status.flagged > 0 ? (
        <Link href="/offline/queue" className="underline">
          {String(status.flagged)} perlu diperiksa
        </Link>
      ) : null}
      {status.notice ? <span>{status.notice}</span> : null}
    </span>
  );
}
