import { usePage } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { offlineQueue } from '@/lib/offline/service';
import { refreshQueue } from '@/lib/offline/store';
import { flushQueue, useQueueItems } from '@/lib/offline/useOfflineStatus';
import { formatDateTime } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

const TONE = { pending: 'warning', conflict: 'danger', rejected: 'danger' } as const;
const LABEL = {
  pending: 'Menunggu dikirim',
  conflict: 'Konflik',
  rejected: 'Ditolak server',
} as const;

/**
 * P3-02: antrean pencatatan offline di perangkat ini. Isi catatan tidak ditampilkan di sini (terenkripsi di perangkat); petugas dapat
 * mengirim sekarang, mengirim ulang yang ditandai, atau membuang.
 */
export default function Queue() {
  const { auth } = usePage().props;
  const userId = auth.user?.id ?? null;
  const timezone = useClinicTimezone();
  const rows = useQueueItems();
  const [busy, setBusy] = useState(false);
  const supported = offlineQueue() !== null;
  const load = () => refreshQueue(userId);

  async function send() {
    if (userId === null) {
      return;
    }
    setBusy(true);
    try {
      await flushQueue(userId);
    } finally {
      setBusy(false);
      await load();
    }
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Antrean offline" />
        <PageHeader
          title="Antrean offline"
          description="Anamnesis dan catatan SOAP yang disimpan saat internet putus, menunggu dikirim."
          actions={
            <Button loading={busy} onClick={() => void send()}>
              Kirim sekarang
            </Button>
          }
        />
        {!supported ? (
          <Alert tone="warning">Peramban ini tidak mendukung penyimpanan offline yang aman.</Alert>
        ) : null}
        <Alert tone="info">
          Isi catatan disimpan terenkripsi di perangkat ini dan dihapus begitu terkirim. Antrean
          yang tertinggal lebih dari 72 jam dibuang. Jangan menutup peramban atau keluar dari akun
          sebelum antrean kosong.
        </Alert>
        {rows.length === 0 ? <p className="text-muted text-sm">Antrean kosong.</p> : null}
        <ul className="flex flex-col gap-2">
          {rows.map((row) => (
            <li
              key={row.id}
              className="card flex flex-wrap items-center gap-x-4 gap-y-1 p-3 text-sm"
            >
              <strong>{row.label}</strong>
              <Badge tone={TONE[row.status]}>{LABEL[row.status]}</Badge>
              <span className="text-muted">
                {formatDateTime(new Date(row.createdAt), timezone)}
              </span>
              {row.message ? <span>{row.message}</span> : null}
              {row.status !== 'pending' ? (
                <Button
                  variant="secondary"
                  onClick={() => {
                    void offlineQueue()?.retry(row.id).then(load);
                  }}
                >
                  Kirim ulang
                </Button>
              ) : null}
              <ConfirmAction
                label="Buang"
                message="Catatan ini dibuang dari perangkat dan tidak akan dikirim. Lanjutkan?"
                confirmLabel="Ya, buang"
                onConfirm={() => {
                  void offlineQueue()?.remove(row.id).then(load);
                }}
              />
            </li>
          ))}
        </ul>
      </main>
    </AppLayout>
  );
}
