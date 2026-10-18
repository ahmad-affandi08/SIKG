import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { MoneyField } from '@/components/ui/MoneyField';
import { TextField } from '@/components/ui/TextField';
import { claimSchema } from '@/features/insurance/schemas';
import type { ClaimValues } from '@/features/insurance/schemas';
import { formatRupiah } from '@/lib/format';

interface Props {
  invoice: {
    id: string;
    number: string | null;
    status_label: string;
    claimable: boolean;
    total_amount: number;
    paid_amount: number;
    committed_amount: number;
    claimable_amount: number;
    patient_name: string;
    medical_record_no: string;
  };
  policies: { id: string; insurer_id: string; label: string; remaining_amount: number | null }[];
  insurers: SelectOption[];
}

/** P2-02: membuat klaim ke penjamin dari satu tagihan. */
export default function Create({ invoice, policies, insurers }: Props) {
  const form = useForm<ClaimValues & { invoice_id: string }>({
    invoice_id: invoice.id,
    insurer_id: '',
    policy_id: '',
    claimed_amount: String(invoice.claimable_amount),
    notes: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const matching = policies.filter(
    (p) => form.data.insurer_id === '' || p.insurer_id === form.data.insurer_id,
  );

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = claimSchema(invoice.claimable_amount).safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post('/insurance/claims');
  }

  const error = (key: keyof ClaimValues): string | undefined => errors[key] ?? form.errors[key];

  return (
    <AppLayout>
      <main className="flex max-w-xl flex-col gap-3 p-3">
        <PageHead title="Klaim baru" />
        <PageHeader
          title="Klaim ke penjamin"
          back={{ href: `/invoices/${invoice.id}`, label: 'tagihan' }}
          description={`Tagihan ${invoice.number ?? '-'} - ${invoice.patient_name} (${invoice.medical_record_no})`}
        />
        <dl className="text-sm">
          <div className="flex gap-3">
            <dt className="text-muted w-48">Total tagihan</dt>
            <dd>{formatRupiah(invoice.total_amount)}</dd>
          </div>
          <div className="flex gap-3">
            <dt className="text-muted w-48">Sudah dibayar</dt>
            <dd>{formatRupiah(invoice.paid_amount)}</dd>
          </div>
          <div className="flex gap-3">
            <dt className="text-muted w-48">Sudah diklaim</dt>
            <dd>{formatRupiah(invoice.committed_amount)}</dd>
          </div>
          <div className="flex gap-3">
            <dt className="text-muted w-48">Dapat diklaim</dt>
            <dd>{formatRupiah(invoice.claimable_amount)}</dd>
          </div>
        </dl>
        {!invoice.claimable ? (
          <Alert tone="warning">
            Tagihan berstatus {invoice.status_label}. Hanya tagihan yang sudah terbit dan belum
            dibatalkan yang dapat diklaim.
          </Alert>
        ) : null}
        <form onSubmit={submit} noValidate aria-label="Klaim baru" className="form-grid">
          <Select
            label="Penjamin"
            placeholder="Pilih penjamin"
            options={insurers}
            value={form.data.insurer_id}
            onChange={(event) => {
              form.setData((data) => ({ ...data, insurer_id: event.target.value, policy_id: '' }));
            }}
            error={error('insurer_id')}
          />
          <Select
            label="Polis pasien (opsional)"
            placeholder="Tanpa polis"
            options={matching.map((p) => ({
              value: p.id,
              label:
                p.remaining_amount === null
                  ? p.label
                  : `${p.label} (sisa plafon ${formatRupiah(p.remaining_amount)})`,
            }))}
            value={form.data.policy_id}
            onChange={(event) => {
              form.setData('policy_id', event.target.value);
            }}
            error={error('policy_id')}
          />
          <MoneyField
            label="Jumlah klaim"
            value={form.data.claimed_amount}
            onValueChange={(digits) => {
              form.setData('claimed_amount', digits);
            }}
            error={error('claimed_amount')}
          />
          <TextField
            label="Catatan"
            value={form.data.notes}
            onChange={(event) => {
              form.setData('notes', event.target.value);
            }}
            error={error('notes')}
          />
          <div className="flex gap-3">
            <Button type="submit" disabled={!invoice.claimable} loading={form.processing}>
              Buat klaim
            </Button>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
