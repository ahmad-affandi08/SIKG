import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { TextField } from '@/components/ui/TextField';
import { useIntentStatus } from '@/features/onlinePayments/queries';
import { INTENT_TONE, startQrSchema } from '@/features/onlinePayments/schemas';
import { formatRupiah } from '@/lib/format';

interface Intent {
  id: string;
  amount: number;
  status: string;
  status_label: string;
  note: string | null;
  expires_at: string;
  paid_at: string | null;
  payment_url?: string | null;
}

interface Props {
  invoice: {
    id: string;
    number: string | null;
    balance: number;
    status: string;
    lifecycle: string;
  };
  ready: boolean;
  active: Intent | null;
  history: Intent[];
}

/** P3-05: QRIS dinamis untuk satu tagihan. Halaman memantau status dan kembali ke tagihan begitu lunas. */
export default function OnlinePayment({ invoice, ready, active, history }: Props) {
  const form = useForm({ amount: '' });
  const [error, setError] = useState<string | null>(null);
  const status = useIntentStatus(invoice.id, active?.id ?? '', active !== null);

  useEffect(() => {
    if (status.data && status.data.status !== 'pending') {
      router.reload();
    }
  }, [status.data]);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = startQrSchema(invoice.balance).safeParse(form.data);
    if (!result.success) {
      setError(result.error.issues[0]?.message ?? 'Jumlah tidak valid.');
      return;
    }
    setError(null);
    form.post(`/invoices/${invoice.id}/online-payments`, { preserveScroll: true });
  }

  return (
    <AppLayout>
      <main className="flex max-w-2xl flex-col gap-3 p-3">
        <PageHead title="Bayar QRIS" />
        <PageHeader
          title={`Bayar QRIS - ${invoice.number ?? 'Tagihan'}`}
          back={{ href: `/invoices/${invoice.id}`, label: 'tagihan' }}
        />
        {!ready ? (
          <Alert tone="warning">
            Pembayaran online belum diaktifkan. Admin mengaturnya di Pengaturan pembayaran online.
          </Alert>
        ) : null}
        <p className="text-sm">
          Sisa tagihan: <strong>{formatRupiah(invoice.balance)}</strong>
        </p>

        {active ? (
          <section aria-label="QR aktif" className="card flex flex-col items-center gap-2 p-4">
            <Badge tone={INTENT_TONE[status.data?.status ?? active.status] ?? 'neutral'}>
              {status.data?.status_label ?? active.status_label}
            </Badge>
            <p className="text-lg font-semibold">{formatRupiah(active.amount)}</p>
            {active.payment_url ? (
              <>
                <a
                  href={active.payment_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="btn btn-primary"
                >
                  Buka halaman QRIS (layar pasien)
                </a>
                <p className="text-muted max-w-md text-center text-xs">
                  Halaman milik DOKU menampilkan kode QRIS. Buka di layar yang dapat dilihat pasien,
                  lalu pasien memindainya dengan aplikasi bank atau dompet digital. Tautan:{' '}
                  <code>{active.payment_url}</code>
                </p>
              </>
            ) : (
              <Alert tone="warning">
                Halaman pembayaran tidak tersedia dari penyedia. Batalkan lalu buat pembayaran baru.
              </Alert>
            )}
            <p className="text-muted text-xs">
              Berlaku sampai {active.expires_at}. Halaman ini memeriksa pembayaran otomatis.
            </p>
            <ConfirmAction
              label="Tutup pembayaran ini"
              message="Pembayaran ini ditutup di sistem. DOKU tidak dapat membatalkan halaman QRIS; bila pasien telanjur membayar, pembayarannya tetap dicatat atau ditandai untuk diselesaikan. Lanjutkan?"
              confirmLabel="Ya, tutup"
              onConfirm={() => {
                router.post(
                  `/invoices/${invoice.id}/online-payments/${active.id}/cancel`,
                  {},
                  { preserveScroll: true },
                );
              }}
            />
          </section>
        ) : ready && invoice.lifecycle === 'issued' && invoice.balance > 0 ? (
          <form onSubmit={submit} noValidate aria-label="Buat QR" className="form-grid">
            <TextField
              label="Jumlah (kosong = seluruh sisa tagihan)"
              inputMode="numeric"
              required={false}
              value={form.data.amount}
              error={error ?? form.errors.amount}
              onChange={(event) => {
                form.setData('amount', event.target.value);
              }}
            />
            <div>
              <Button type="submit" loading={form.processing}>
                Buat QR pembayaran
              </Button>
            </div>
          </form>
        ) : (
          <Alert tone="info">Tagihan ini tidak dapat dibayar online saat ini.</Alert>
        )}

        {history.length > 0 ? (
          <section aria-label="Riwayat QR" className="flex flex-col gap-1">
            <h2>Riwayat QR</h2>
            <ul className="flex flex-col gap-1 text-sm">
              {history.map((intent) => (
                <li
                  key={intent.id}
                  className="card flex flex-wrap items-center gap-x-4 gap-y-1 p-2"
                >
                  <Badge tone={INTENT_TONE[intent.status] ?? 'neutral'}>
                    {intent.status_label}
                  </Badge>
                  <span>{formatRupiah(intent.amount)}</span>
                  {intent.paid_at ? (
                    <span className="text-muted">dibayar {intent.paid_at}</span>
                  ) : null}
                  {intent.note ? <span className="text-muted">({intent.note})</span> : null}
                </li>
              ))}
            </ul>
          </section>
        ) : null}
        <p className="text-sm">
          <Link href="/online-payments" className="underline">
            Daftar rekonsiliasi pembayaran online
          </Link>
        </p>
      </main>
    </AppLayout>
  );
}
