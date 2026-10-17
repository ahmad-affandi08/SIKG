import { router } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { fetchWhatsAppLink } from '@/features/reminders/api';
import { formatDateTime } from '@/lib/format';
import { HttpError } from '@/lib/http';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Row {
  id: string;
  kind: string;
  kind_label: string;
  patient_name: string;
  medical_record_no: string;
  has_phone: boolean;
  status: string;
  status_label: string;
  channel: string | null;
  sent_at: string | null;
  last_error: string | null;
  due_at: string;
  message?: string;
}

interface Props {
  enabled: boolean;
  mode: 'link' | 'api';
  pending: Row[];
  history: Row[];
  optouts: { patient_id: string; patient_name: string; medical_record_no: string }[];
}

const TONE: Record<string, 'success' | 'danger' | 'warning' | 'neutral'> = {
  sent: 'success',
  failed: 'danger',
  pending: 'warning',
  skipped: 'neutral',
};

/** P2-03: meja pengingat. Petugas membuka WhatsApp dengan satu klik, lalu menandai terkirim. */
export default function Index({ enabled, mode, pending, history, optouts }: Props) {
  const timezone = useClinicTimezone();
  const [error, setError] = useState<string | null>(null);

  async function openWhatsApp(id: string) {
    setError(null);
    // Jendela dibuka dulu di dalam klik agar tidak diblokir peramban; tujuannya diisi setelah tautan diterima.
    const win = window.open('', '_blank');
    try {
      const url = await fetchWhatsAppLink(id);
      if (win) {
        win.opener = null;
        win.location.href = url;
      }
    } catch (failure) {
      win?.close();
      setError(
        failure instanceof HttpError ? failure.message : 'Tautan WhatsApp tidak dapat dibuat.',
      );
    }
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title="Pengingat pasien" />
        <PageHeader
          title="Pengingat pasien"
          description="Pengingat janji (H-1 dan hari-H) dan ajakan kontrol berkala. Pesan dirakit dari templat dan tidak mencantumkan diagnosa."
        />

        {!enabled ? (
          <Alert tone="info">
            Pengingat belum diaktifkan. Admin dapat mengaturnya di Pengaturan pengingat.
          </Alert>
        ) : null}
        {mode === 'api' ? (
          <Alert tone="info">
            Mode API aktif: pengingat dikirim otomatis. Daftar menunggu di bawah hanya berisi yang
            belum terkirim.
          </Alert>
        ) : null}
        {error ? <Alert tone="error">{error}</Alert> : null}

        <section aria-label="Menunggu" className="flex flex-col gap-2">
          <h2>Menunggu dikirim ({pending.length})</h2>
          {pending.length === 0 ? (
            <p className="text-muted text-sm">Tidak ada pengingat yang menunggu.</p>
          ) : (
            <table className="w-full text-left text-sm">
              <caption className="sr-only">Pengingat menunggu</caption>
              <thead>
                <tr>
                  <th scope="col">Jenis</th>
                  <th scope="col">Pasien</th>
                  <th scope="col">Pesan</th>
                  <th scope="col">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {pending.map((r) => (
                  <tr key={r.id}>
                    <td>{r.kind_label}</td>
                    <td>
                      {r.patient_name} ({r.medical_record_no})
                      {!r.has_phone ? (
                        <div className="text-danger-600">Belum ada nomor HP</div>
                      ) : null}
                    </td>
                    <td className="max-w-md">{r.message}</td>
                    <td>
                      <div className="flex flex-wrap gap-2">
                        {mode === 'link' ? (
                          <>
                            <Button
                              variant="secondary"
                              disabled={!r.has_phone}
                              onClick={() => {
                                void openWhatsApp(r.id);
                              }}
                            >
                              Buka WhatsApp
                            </Button>
                            <Button
                              variant="secondary"
                              onClick={() => {
                                router.post(
                                  `/reminders/${r.id}/sent`,
                                  {},
                                  { preserveScroll: true },
                                );
                              }}
                            >
                              Tandai terkirim
                            </Button>
                          </>
                        ) : null}
                        <Button
                          variant="secondary"
                          onClick={() => {
                            router.post(`/reminders/${r.id}/skip`, {}, { preserveScroll: true });
                          }}
                        >
                          Lewati
                        </Button>
                        <ConfirmAction
                          label="Jangan ingatkan lagi"
                          message="Pasien ini tidak akan menerima pengingat otomatis lagi sampai diaktifkan kembali."
                          confirmLabel="Ya, jangan ingatkan"
                          onConfirm={() => {
                            router.post(`/reminders/${r.id}/opt-out`, {}, { preserveScroll: true });
                          }}
                        />
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        <section aria-label="Riwayat" className="flex flex-col gap-2">
          <h2>Riwayat terakhir</h2>
          {history.length === 0 ? (
            <p className="text-muted text-sm">Belum ada riwayat.</p>
          ) : (
            <table className="w-full text-left text-sm">
              <caption className="sr-only">Riwayat pengingat</caption>
              <thead>
                <tr>
                  <th scope="col">Jenis</th>
                  <th scope="col">Pasien</th>
                  <th scope="col">Status</th>
                  <th scope="col">Keterangan</th>
                </tr>
              </thead>
              <tbody>
                {history.map((r) => (
                  <tr key={r.id}>
                    <td>{r.kind_label}</td>
                    <td>
                      {r.patient_name} ({r.medical_record_no})
                    </td>
                    <td>
                      <Badge tone={TONE[r.status] ?? 'neutral'}>{r.status_label}</Badge>
                    </td>
                    <td>
                      {r.last_error ??
                        (r.sent_at
                          ? `${formatDateTime(r.sent_at, timezone)} lewat ${r.channel === 'api' ? 'API' : 'tautan WhatsApp'}`
                          : '-')}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        {optouts.length > 0 ? (
          <section aria-label="Tidak diingatkan" className="flex flex-col gap-2">
            <h2>Pasien yang tidak mau diingatkan</h2>
            <ul className="flex flex-col gap-1 text-sm">
              {optouts.map((o) => (
                <li key={o.patient_id} className="flex items-center gap-3">
                  <span>
                    {o.patient_name} ({o.medical_record_no})
                  </span>
                  <Button
                    variant="secondary"
                    onClick={() => {
                      router.post(
                        `/reminders/patients/${o.patient_id}/opt-in`,
                        {},
                        { preserveScroll: true },
                      );
                    }}
                  >
                    Ingatkan kembali
                  </Button>
                </li>
              ))}
            </ul>
          </section>
        ) : null}
      </main>
    </AppLayout>
  );
}
