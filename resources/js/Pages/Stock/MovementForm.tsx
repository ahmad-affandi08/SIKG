import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { DatePicker } from '@/components/ui/DatePicker';
import { stockMovementSchema } from '@/features/pharmacy/schemas';

interface Props {
  medicines: { id: string; name: string; unit: string; is_active: boolean }[];
  preset: { medicine: string; type: string };
  can: { override: boolean };
}

const TYPE_OPTIONS: SelectOption[] = [
  { value: 'in', label: 'Stok masuk' },
  { value: 'out', label: 'Stok keluar (pemakaian atau penjualan)' },
];

export default function MovementForm({ medicines, preset, can }: Props) {
  const { flash } = usePage().props;
  const options: SelectOption[] = medicines
    .filter((m) => m.is_active)
    .map((m) => ({ value: m.id, label: `${m.name} (${m.unit})` }));
  const [move, setMove] = useState({
    medicine_id: preset.medicine,
    type: preset.type,
    quantity: '',
    batch_no: '',
    expiry_date: '',
    reason: '',
  });
  const [overrideReason, setOverrideReason] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const stockShort = flash.error_code === 'stock.insufficient';

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = stockMovementSchema.safeParse(move);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      '/stock/movements',
      {
        medicine_id: move.medicine_id,
        type: move.type,
        quantity: Number(move.quantity),
        batch_no: move.batch_no || null,
        expiry_date: move.expiry_date || null,
        reason: move.reason || null,
        override_reason: overrideReason || null,
      },
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
    <AppLayout>
      <main className="mx-auto flex max-w-2xl flex-col gap-8 p-6 lg:px-12 lg:py-10">
        <Head title="Stok masuk atau keluar" />
        <PageHeader title="Stok masuk atau keluar" back={{ href: '/stock', label: 'stok obat' }} />
        <form onSubmit={submit} noValidate aria-label="Mutasi stok" className="flex flex-col gap-5">
          {stockShort && flash.error ? <Alert tone="error">{flash.error}</Alert> : null}
          <Select
            label="Obat"
            placeholder="Pilih obat"
            options={options}
            value={move.medicine_id}
            error={errors.medicine_id}
            onChange={(e) => {
              setMove({ ...move, medicine_id: e.target.value });
            }}
          />
          <Select
            label="Jenis mutasi"
            options={TYPE_OPTIONS}
            value={move.type}
            onChange={(e) => {
              setMove({ ...move, type: e.target.value });
            }}
          />
          <TextField
            label="Jumlah"
            inputMode="numeric"
            value={move.quantity}
            error={errors.quantity}
            onChange={(e) => {
              setMove({ ...move, quantity: e.target.value });
            }}
          />
          {move.type === 'in' ? (
            <>
              <TextField
                label="Nomor batch (opsional)"
                value={move.batch_no}
                error={errors.batch_no}
                onChange={(e) => {
                  setMove({ ...move, batch_no: e.target.value });
                }}
              />
              <DatePicker
                label="Tanggal kedaluwarsa (opsional)"
                value={move.expiry_date}
                error={errors.expiry_date}
                maxYear={new Date().getFullYear() + 15}
                onChange={(value) => {
                  setMove({ ...move, expiry_date: value });
                }}
              />
            </>
          ) : null}
          <TextField
            label={move.type === 'out' ? 'Alasan (wajib)' : 'Keterangan (opsional)'}
            value={move.reason}
            error={errors.reason}
            onChange={(e) => {
              setMove({ ...move, reason: e.target.value });
            }}
          />
          {move.type === 'out' && can.override && stockShort ? (
            <TextField
              label="Alasan melebihi stok (khusus Admin)"
              value={overrideReason}
              onChange={(e) => {
                setOverrideReason(e.target.value);
              }}
            />
          ) : null}
          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={saving}>
              Simpan mutasi
            </Button>
            <Link href="/stock" className="btn btn-secondary">
              Batal
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
