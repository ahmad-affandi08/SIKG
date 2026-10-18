import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { TextField } from '@/components/ui/TextField';
import { InvoiceFormShell } from '@/features/billing/components/InvoiceFormShell';
import { voidSchema } from '@/features/billing/schemas';
import type { InvoiceHead } from '@/features/billing/schemas';
import { collectErrors } from '@/features/scheduling/form';

/** Pembatalan tagihan (KSR-07): Admin atau Pemilik dengan alasan; uang yang masuk dikembalikan lewat refund. */
export default function VoidForm({ invoice }: { invoice: InvoiceHead }) {
  const [reason, setReason] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [confirming, setConfirming] = useState(false);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = voidSchema.safeParse({ reason });
    if (!result.success) {
      setErrors(collectErrors(result.error.issues));
      return;
    }
    setErrors({});
    setConfirming(true);
  }

  function send() {
    setConfirming(false);
    setSaving(true);
    router.post(
      `/invoices/${invoice.id}/void`,
      { reason },
      {
        onError: (serverErrors) => {
          setErrors(serverErrors);
        },
        onFinish: () => {
          setSaving(false);
        },
      },
    );
  }

  return (
    <InvoiceFormShell title="Batalkan tagihan" invoice={invoice} showAmounts>
      <ConfirmDialog
        open={confirming}
        title="Batalkan tagihan?"
        variant="danger"
        message={`Tagihan ${invoice.number ?? '(draft)'}${invoice.patient_name ? ` atas nama ${invoice.patient_name}` : ''} akan berstatus Dibatalkan dan tidak dapat dihidupkan kembali. Pembayaran yang sudah masuk dikembalikan lewat refund.`}
        confirmLabel="Ya, batalkan"
        onCancel={() => {
          setConfirming(false);
        }}
        onConfirm={send}
      />
      <form onSubmit={submit} noValidate aria-label="Batalkan tagihan" className="form-grid">
        <Alert tone="warning">
          Tagihan tidak dihapus, hanya berstatus Dibatalkan. Pembayaran yang sudah masuk
          dikembalikan lewat transaksi refund per metode. Tindakan ini tidak dapat dibatalkan.
        </Alert>
        {errors.domain ? (
          <p role="alert" className="text-danger-600">
            {errors.domain}
          </p>
        ) : null}
        <TextField
          field="reason"
          label="Alasan pembatalan"
          value={reason}
          error={errors.reason}
          onChange={(e) => {
            setReason(e.target.value);
          }}
        />
        <div className="flex flex-wrap gap-2">
          <Button type="submit" variant="danger" loading={saving}>
            Batalkan tagihan
          </Button>
          <Link href={`/invoices/${invoice.id}`} className="btn btn-secondary">
            Kembali
          </Link>
        </div>
      </form>
    </InvoiceFormShell>
  );
}
