import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { TextField } from '@/components/ui/TextField';
import { fetchBookingPhone } from '@/features/booking/api';
import { BOOKING_TONE, rejectSchema } from '@/features/booking/schemas';
import { PatientPicker } from '@/features/patient/components/PatientPicker';
import { formatDate } from '@/lib/format';
import { HttpError } from '@/lib/http';

interface Row {
  id: string;
  status: string;
  status_label: string;
  patient_name: string;
  phone_masked: string;
  doctor: string;
  date: string;
  time: string;
  minutes: number;
  complaint: string | null;
  verified: boolean;
  appointment_id: string | null;
  decision_note: string | null;
  matches: { id: string; medical_record_no: string; name: string }[];
  requested_at: string;
}

interface Props {
  requests: Row[];
  public_path: string | null;
}

/** P3-01: meja permintaan booking online. Staf mengaitkan ke pasien terdaftar lalu mengonfirmasi menjadi janji, atau menolak. */
export default function Index({ requests, public_path: publicPath }: Props) {
  const open = requests.filter((r) => r.status === 'pending' || r.status === 'awaiting_code');
  const done = requests.filter((r) => r.status !== 'pending' && r.status !== 'awaiting_code');

  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title="Booking online" />
        <PageHeader
          title="Booking online"
          description="Permintaan dari halaman publik klinik. Konfirmasi mengubahnya menjadi janji di jadwal."
          actions={
            <Link href="/settings/booking" className="btn btn-secondary">
              Pengaturan
            </Link>
          }
        />
        {publicPath ? (
          <Alert tone="info">
            Halaman publik: <code>{publicPath}</code> (bagikan alamat lengkap situs diikuti alamat
            ini).
          </Alert>
        ) : (
          <Alert tone="warning">
            Booking online belum diaktifkan. Atur alamat halaman dan aktifkan di Pengaturan.
          </Alert>
        )}

        <section aria-label="Menunggu keputusan" className="flex flex-col gap-2">
          <h2>Menunggu ({String(open.length)})</h2>
          {open.length === 0 ? (
            <EmptyState
              kind="calendar"
              size="compact"
              title="Tidak ada permintaan yang menunggu"
              description="Permintaan baru dari halaman booking publik akan muncul di sini."
            />
          ) : null}
          {open.map((row) => (
            <RequestCard key={row.id} row={row} />
          ))}
        </section>

        <section aria-label="Riwayat" className="flex flex-col gap-2">
          <h2>Riwayat</h2>
          {done.length === 0 ? (
            <EmptyState kind="folder" size="compact" title="Belum ada riwayat keputusan" />
          ) : null}
          <ul className="flex flex-col gap-1 text-sm">
            {done.map((row) => (
              <li key={row.id} className="card flex flex-wrap items-center gap-x-4 gap-y-1 p-2">
                <Badge tone={BOOKING_TONE[row.status] ?? 'neutral'}>{row.status_label}</Badge>
                <span>{row.patient_name}</span>
                <span className="text-muted">
                  {formatDate(row.date)} {row.time} - {row.doctor}
                </span>
                {row.decision_note ? (
                  <span className="text-muted">({row.decision_note})</span>
                ) : null}
                {row.appointment_id ? (
                  <Link href={`/appointments/${row.appointment_id}/edit`} className="underline">
                    Lihat janji
                  </Link>
                ) : null}
              </li>
            ))}
          </ul>
        </section>
      </main>
    </AppLayout>
  );
}

function RequestCard({ row }: { row: Row }) {
  const [phone, setPhone] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [picked, setPicked] = useState<{ id: string; name: string } | null>(null);
  const [contacted, setContacted] = useState(false);
  const [rejecting, setRejecting] = useState(false);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const pending = row.status === 'pending';

  async function reveal() {
    setError(null);
    try {
      setPhone(await fetchBookingPhone(row.id));
    } catch (failure) {
      setError(failure instanceof HttpError ? failure.message : 'Nomor tidak dapat dibuka.');
    }
  }

  function confirm() {
    if (!picked) {
      setError('Pilih pasien terdaftar lebih dulu.');
      return;
    }
    setError(null);
    router.post(
      `/booking-requests/${row.id}/confirm`,
      { patient_id: picked.id, contacted },
      {
        preserveScroll: true,
        onStart: () => {
          setBusy(true);
        },
        onFinish: () => {
          setBusy(false);
        },
      },
    );
  }

  function reject() {
    const result = rejectSchema.safeParse({ reason });
    if (!result.success) {
      setError(result.error.issues[0]?.message ?? 'Alasan tidak valid.');
      return;
    }
    setError(null);
    router.post(`/booking-requests/${row.id}/reject`, { reason }, { preserveScroll: true });
  }

  return (
    <article className="card flex flex-col gap-2 p-3" aria-label={`Permintaan ${row.patient_name}`}>
      <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
        <strong>{row.patient_name}</strong>
        <Badge tone={BOOKING_TONE[row.status] ?? 'neutral'}>{row.status_label}</Badge>
        <span className="text-sm">
          {formatDate(row.date)} {row.time} ({String(row.minutes)} menit) - {row.doctor}
        </span>
        <span className="text-muted text-xs">masuk {row.requested_at}</span>
      </div>
      {row.complaint ? <p className="text-sm">Keluhan: {row.complaint}</p> : null}
      <p className="text-sm">
        HP: {phone ?? row.phone_masked}{' '}
        {phone === null ? (
          <button type="button" className="underline" onClick={() => void reveal()}>
            Tampilkan
          </button>
        ) : null}{' '}
        {row.verified ? (
          <Badge tone="success">Nomor terverifikasi</Badge>
        ) : (
          <Badge tone="warning">Belum terverifikasi</Badge>
        )}
      </p>
      {error ? <Alert tone="error">{error}</Alert> : null}

      {pending ? (
        <div className="flex flex-col gap-2">
          {row.matches.length > 0 ? (
            <fieldset className="flex flex-col gap-1">
              <legend className="text-sm font-semibold">Pasien terdaftar dengan nomor ini</legend>
              {row.matches.map((m) => (
                <label key={m.id} className="flex items-center gap-2 text-sm">
                  <input
                    type="radio"
                    name={`patient-${row.id}`}
                    checked={picked?.id === m.id}
                    onChange={() => {
                      setPicked({
                        id: m.id,
                        name: m.name,
                      });
                    }}
                  />
                  {m.name} ({m.medical_record_no})
                </label>
              ))}
            </fieldset>
          ) : (
            <p className="text-muted text-sm">
              Tidak ada pasien dengan nomor ini. Cari pasien di bawah atau{' '}
              <Link href="/patients/create" className="underline">
                daftarkan pasien baru
              </Link>{' '}
              lalu kembali ke sini.
            </p>
          )}
          <PatientPicker
            selected={picked}
            onSelect={(p) => {
              setPicked(p ? { id: p.id, name: p.name } : null);
            }}
          />
          {!row.verified ? (
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={contacted}
                onChange={(event) => {
                  setContacted(event.target.checked);
                }}
              />
              Pasien sudah dihubungi dan membenarkan permintaan ini
            </label>
          ) : null}
          <div className="flex flex-wrap items-center gap-2">
            <Button loading={busy} onClick={confirm}>
              Konfirmasi menjadi janji
            </Button>
            <Button
              variant="secondary"
              onClick={() => {
                setRejecting((value) => !value);
              }}
            >
              Tolak
            </Button>
          </div>
          {rejecting ? (
            <div className="flex flex-wrap items-end gap-2">
              <TextField
                label="Alasan penolakan"
                required
                value={reason}
                onChange={(event) => {
                  setReason(event.target.value);
                }}
              />
              <Button variant="danger" onClick={reject}>
                Tolak permintaan
              </Button>
            </div>
          ) : null}
        </div>
      ) : (
        <p className="text-muted text-sm">
          Menunggu pasien memverifikasi nomor lewat kode WhatsApp.
        </p>
      )}
    </article>
  );
}
