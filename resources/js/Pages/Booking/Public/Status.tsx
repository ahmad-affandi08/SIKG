import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { TextField } from '@/components/ui/TextField';
import { BOOKING_TONE, verifyCodeSchema } from '@/features/booking/schemas';
import { formatDate } from '@/lib/format';

interface Props {
  slug: string;
  token: string;
  booking: {
    status: string;
    status_label: string;
    first_name: string;
    doctor: string;
    date: string;
    time: string;
    minutes: number;
    verified: boolean;
    can_cancel: boolean;
    can_verify: boolean;
    note: string | null;
  };
}

/** P3-01: status permintaan booking (dan isian kode verifikasi bila diperlukan). Alamatnya memuat token acak, bukan data pasien. */
export default function Status({ slug, token, booking }: Props) {
  const { flash } = usePage().props;
  const form = useForm({ code: '' });
  const [error, setError] = useState<string | null>(null);
  const base = `/book/${slug}/r/${token}`;

  function verify(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = verifyCodeSchema.safeParse(form.data);
    if (!result.success) {
      setError(result.error.issues[0]?.message ?? 'Kode tidak valid.');
      return;
    }
    setError(null);
    form.post(`${base}/verify`, { preserveScroll: true });
  }

  return (
    <div className="flex min-h-screen items-start justify-center p-3">
      <main className="pane w-full max-w-xl shadow-[3px_3px_6px_rgb(0_0_0/0.3)]">
        <Head title="Status booking" />
        <div className="pane-head status-bar border-0 text-white">
          <span>Status booking</span>
        </div>
        <div className="flex flex-col gap-3 p-4">
          {flash.success ? <Alert tone="success">{flash.success}</Alert> : null}
          {flash.error ? <Alert tone="error">{flash.error}</Alert> : null}
          <p className="text-sm">
            Halo {booking.first_name}. Permintaan Anda untuk <strong>{booking.doctor}</strong> pada{' '}
            <strong>
              {formatDate(booking.date)} pukul {booking.time}
            </strong>{' '}
            ({String(booking.minutes)} menit):
          </p>
          <p>
            <Badge tone={BOOKING_TONE[booking.status] ?? 'neutral'}>{booking.status_label}</Badge>
          </p>
          {booking.status === 'pending' ? (
            <Alert tone="info">
              Permintaan sedang ditinjau klinik. Simpan halaman ini untuk memeriksa statusnya. Janji
              pasti setelah berstatus Dikonfirmasi.
            </Alert>
          ) : null}
          {booking.status === 'confirmed' ? (
            <Alert tone="success">Janji Anda sudah masuk jadwal klinik. Sampai jumpa.</Alert>
          ) : null}
          {booking.status === 'rejected' ? (
            <Alert tone="warning">
              Permintaan tidak dapat dipenuhi
              {booking.note ? `: ${booking.note}` : '.'} Silakan pilih waktu lain.
            </Alert>
          ) : null}

          {booking.can_verify ? (
            <form
              onSubmit={verify}
              noValidate
              aria-label="Verifikasi nomor HP"
              className="form-grid"
            >
              <TextField
                label="Kode verifikasi (6 angka dari WhatsApp)"
                required
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={6}
                value={form.data.code}
                error={error ?? form.errors.code}
                onChange={(event) => {
                  form.setData('code', event.target.value);
                }}
              />
              <div className="flex flex-wrap gap-2">
                <Button type="submit" loading={form.processing}>
                  Verifikasi
                </Button>
                <Button
                  variant="secondary"
                  onClick={() => {
                    router.post(`${base}/resend`, {}, { preserveScroll: true });
                  }}
                >
                  Kirim ulang kode
                </Button>
              </div>
            </form>
          ) : null}

          {booking.can_cancel ? (
            <div>
              <ConfirmAction
                label="Batalkan permintaan"
                message="Permintaan ini dibatalkan dan slot dilepas. Lanjutkan?"
                confirmLabel="Ya, batalkan"
                onConfirm={() => {
                  router.post(`${base}/cancel`, {}, { preserveScroll: true });
                }}
              />
            </div>
          ) : null}
          <p className="text-muted text-xs">
            <a href={`/book/${slug}`} className="underline">
              Buat permintaan baru
            </a>
          </p>
        </div>
      </main>
    </div>
  );
}
