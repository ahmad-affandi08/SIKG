import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { adjustmentSchema } from '@/features/pharmacy/schemas';

interface Props {
  medicines: { id: string; name: string; unit: string; quantity: number; is_active: boolean }[];
  preset: { medicine: string };
}

export default function AdjustmentForm({ medicines, preset }: Props) {
  const options: SelectOption[] = medicines
    .filter((m) => m.is_active)
    .map((m) => ({ value: m.id, label: `${m.name} (tercatat ${String(m.quantity)} ${m.unit})` }));
  const [adjust, setAdjust] = useState({
    medicine_id: preset.medicine,
    counted_quantity: '',
    reason: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = adjustmentSchema.safeParse(adjust);
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
      '/stock/adjustments',
      {
        medicine_id: adjust.medicine_id,
        counted_quantity: Number(adjust.counted_quantity),
        reason: adjust.reason,
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
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Penyesuaian stok" />
        <PageHeader
          title="Penyesuaian stok"
          back={{ href: '/stock', label: 'stok obat' }}
          description="Samakan stok tercatat dengan hasil hitung fisik (opname)."
        />
        <form onSubmit={submit} noValidate aria-label="Penyesuaian stok" className="form-grid">
          <Select
            field="medicine_id"
            label="Obat"
            placeholder="Pilih obat"
            options={options}
            value={adjust.medicine_id}
            error={errors.medicine_id}
            onChange={(e) => {
              setAdjust({ ...adjust, medicine_id: e.target.value });
            }}
          />
          <TextField
            field="counted_quantity"
            label="Jumlah fisik terhitung"
            inputMode="numeric"
            value={adjust.counted_quantity}
            error={errors.counted_quantity}
            onChange={(e) => {
              setAdjust({ ...adjust, counted_quantity: e.target.value });
            }}
          />
          <TextField
            field="reason"
            label="Alasan penyesuaian"
            value={adjust.reason}
            error={errors.reason}
            onChange={(e) => {
              setAdjust({ ...adjust, reason: e.target.value });
            }}
          />
          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={saving}>
              Simpan penyesuaian
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
