import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { onlineSettingsSchema } from '@/features/onlinePayments/schemas';

interface Props {
  settings: {
    enabled: boolean;
    environment: 'sandbox' | 'production';
    merchant_id: string;
    has_key: boolean;
    payment_method_id: string;
    expiry_minutes: number;
    webhook_path: string;
    methods: { value: string; label: string }[];
  };
}

type Key = 'merchant_id' | 'server_key' | 'payment_method_id' | 'expiry_minutes' | 'environment';

/** P3-05: pengaturan payment gateway (Midtrans QRIS). Server key hanya ditulis; tidak pernah ditampilkan kembali (M13). */
export default function OnlinePayments({ settings }: Props) {
  const form = useForm({
    enabled: settings.enabled,
    environment: settings.environment,
    merchant_id: settings.merchant_id,
    server_key: '',
    payment_method_id: settings.payment_method_id,
    expiry_minutes: String(settings.expiry_minutes),
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const err = (key: Key) => errors[key] ?? (form.errors as Record<string, string | undefined>)[key];

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = onlineSettingsSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post('/settings/online-payments', {
      preserveScroll: true,
      onSuccess: () => {
        form.reset('server_key');
      },
    });
  }

  return (
    <AppLayout>
      <main className="flex max-w-xl flex-col gap-3 p-3">
        <PageHead title="Pengaturan pembayaran online" />
        <PageHeader
          title="Pembayaran online"
          description="QRIS dinamis lewat Midtrans. Pembayaran yang masuk dicatat ke tagihan otomatis."
        />
        <Alert tone="info">
          Daftarkan alamat ini sebagai Payment Notification URL di dasbor Midtrans:{' '}
          <code>{settings.webhook_path}</code> (didahului alamat lengkap situs ini). Jangan bagikan
          alamat itu.
        </Alert>
        <form
          onSubmit={submit}
          noValidate
          aria-label="Pengaturan pembayaran online"
          className="form-grid"
        >
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.data.enabled}
              onChange={(event) => {
                form.setData('enabled', event.target.checked);
              }}
            />
            Aktifkan pembayaran online
          </label>
          <Select
            label="Lingkungan"
            required
            value={form.data.environment}
            error={err('environment')}
            options={[
              { value: 'sandbox', label: 'Sandbox (uji coba)' },
              { value: 'production', label: 'Production' },
            ]}
            onChange={(event) => {
              form.setData('environment', event.target.value as 'sandbox' | 'production');
            }}
          />
          <TextField
            label="ID merchant (opsional)"
            value={form.data.merchant_id}
            error={err('merchant_id')}
            onChange={(event) => {
              form.setData('merchant_id', event.target.value);
            }}
          />
          <TextField
            label={settings.has_key ? 'Server key (kosongkan untuk mempertahankan)' : 'Server key'}
            type="password"
            autoComplete="off"
            required={false}
            value={form.data.server_key}
            error={err('server_key')}
            onChange={(event) => {
              form.setData('server_key', event.target.value);
            }}
          />
          <Select
            label="Metode pembayaran untuk catatan"
            required
            placeholder="Pilih metode (disarankan jenis QRIS)"
            value={form.data.payment_method_id}
            error={err('payment_method_id')}
            options={settings.methods}
            onChange={(event) => {
              form.setData('payment_method_id', event.target.value);
            }}
          />
          <TextField
            label="Masa berlaku QR (menit)"
            inputMode="numeric"
            required
            value={form.data.expiry_minutes}
            error={err('expiry_minutes')}
            onChange={(event) => {
              form.setData('expiry_minutes', event.target.value);
            }}
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
