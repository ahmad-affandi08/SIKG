import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { loyaltySettingsSchema } from '@/features/loyalty/schemas';

interface Props {
  settings: {
    enabled: boolean;
    rupiah_per_point: number;
    point_value_amount: number;
    min_redeem_points: number;
  };
}

/** P3-06: aturan poin loyalitas klinik. */
export default function Loyalty({ settings }: Props) {
  const form = useForm({
    enabled: settings.enabled,
    rupiah_per_point: String(settings.rupiah_per_point),
    point_value_amount: String(settings.point_value_amount),
    min_redeem_points: String(settings.min_redeem_points),
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const err = (key: 'rupiah_per_point' | 'point_value_amount' | 'min_redeem_points') =>
    errors[key] ?? form.errors[key];

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = loyaltySettingsSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post('/settings/loyalty', { preserveScroll: true });
  }

  const text = (key: 'rupiah_per_point' | 'point_value_amount' | 'min_redeem_points') => ({
    value: form.data[key],
    onChange: (event: { target: { value: string } }) => {
      form.setData(key, event.target.value);
    },
    error: err(key),
  });

  return (
    <AppLayout>
      <main className="flex max-w-xl flex-col gap-3 p-3">
        <PageHead title="Pengaturan poin" />
        <PageHeader
          title="Poin loyalitas"
          description="Pasien mendapat poin saat tagihan lunas dan dapat menukarnya menjadi diskon tagihan."
        />
        <form onSubmit={submit} noValidate aria-label="Pengaturan poin" className="form-grid">
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.data.enabled}
              onChange={(event) => {
                form.setData('enabled', event.target.checked);
              }}
            />
            Aktifkan poin loyalitas
          </label>
          <TextField
            label="Belanja per 1 poin (Rp)"
            inputMode="numeric"
            required
            {...text('rupiah_per_point')}
          />
          <TextField
            label="Nilai 1 poin saat ditukar (Rp)"
            inputMode="numeric"
            required
            {...text('point_value_amount')}
          />
          <TextField
            label="Minimum poin sekali tukar"
            inputMode="numeric"
            required
            {...text('min_redeem_points')}
          />
          <div>
            <Button type="submit" loading={form.processing}>
              Simpan
            </Button>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
