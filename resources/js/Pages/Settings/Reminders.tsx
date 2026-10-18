import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { TextArea } from '@/components/ui/TextArea';
import { TextField } from '@/components/ui/TextField';
import { reminderSettingsSchema } from '@/features/reminders/schemas';
import type { ReminderSettingsValues } from '@/features/reminders/schemas';

interface Props {
  settings: {
    enabled: boolean;
    mode: 'link' | 'api';
    recall_months: number;
    api_phone_number_id: string | null;
    api_language: string;
    has_token: boolean;
    api_ready: boolean;
  };
  kinds: {
    kind: 'h1' | 'h0' | 'recall';
    label: string;
    enabled: boolean;
    template: string;
    default_template: string;
    api_template: string;
    api_parameters: string[];
  }[];
  placeholders: string[];
}

/** P2-03: pengaturan pengingat (jenis, templat, mode tautan atau API). */
export default function Reminders({ settings, kinds, placeholders }: Props) {
  const form = useForm<ReminderSettingsValues>({
    enabled: settings.enabled,
    mode: settings.mode,
    recall_months: String(settings.recall_months),
    api_phone_number_id: settings.api_phone_number_id ?? '',
    api_token: '',
    api_language: settings.api_language,
    kinds: Object.fromEntries(
      kinds.map((k) => [
        k.kind,
        { enabled: k.enabled, template: k.template, api_template: k.api_template },
      ]),
    ) as ReminderSettingsValues['kinds'],
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = reminderSettingsSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[issue.path.join('.')] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.put('/settings/reminders', {
      preserveScroll: true,
      onSuccess: () => {
        form.setData('api_token', '');
      },
    });
  }

  const error = (key: string): string | undefined =>
    errors[key] ?? (form.errors as Record<string, string | undefined>)[key];
  const setKind = (
    kind: 'h1' | 'h0' | 'recall',
    patch: Partial<ReminderSettingsValues['kinds']['h1']>,
  ) => {
    form.setData('kinds', { ...form.data.kinds, [kind]: { ...form.data.kinds[kind], ...patch } });
  };

  return (
    <AppLayout>
      <main className="flex max-w-3xl flex-col gap-4 p-3">
        <PageHead title="Pengaturan pengingat" />
        <PageHeader
          title="Pengaturan pengingat"
          description="Pengingat janji dan kontrol berkala ke pasien lewat nomor HP yang tercatat."
        />
        <form onSubmit={submit} noValidate aria-label="Pengaturan pengingat" className="form-grid">
          <label className="min-h-touch flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.data.enabled}
              onChange={(event) => {
                form.setData('enabled', event.target.checked);
              }}
            />
            <span>Aktifkan pengingat pasien</span>
          </label>
          <Select
            required
            label="Cara pengiriman"
            options={[
              {
                value: 'link',
                label: 'Tautan WhatsApp - petugas membuka dan menekan kirim (gratis)',
              },
              {
                value: 'api',
                label: 'WhatsApp Business API - terkirim otomatis (berbiaya sesuai penyedia)',
              },
            ]}
            value={form.data.mode}
            onChange={(event) => {
              form.setData('mode', event.target.value as 'link' | 'api');
            }}
            error={error('mode')}
          />
          <TextField
            required
            label="Kontrol berkala setelah kunjungan terakhir (bulan)"
            inputMode="numeric"
            value={form.data.recall_months}
            onChange={(event) => {
              form.setData('recall_months', event.target.value);
            }}
            error={error('recall_months')}
          />

          {kinds.map((k) => (
            <fieldset key={k.kind} className="form-grid">
              <legend className="text-lg font-semibold">{k.label}</legend>
              <label className="min-h-touch flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.data.kinds[k.kind].enabled}
                  onChange={(event) => {
                    setKind(k.kind, { enabled: event.target.checked });
                  }}
                />
                <span>Kirim jenis pengingat ini</span>
              </label>
              <TextArea
                label="Templat pesan (kosong = bawaan)"
                value={form.data.kinds[k.kind].template}
                placeholder={k.default_template}
                onChange={(event) => {
                  setKind(k.kind, { template: event.target.value });
                }}
                error={error(`kinds.${k.kind}.template`)}
              />
              {form.data.mode === 'api' ? (
                <TextField
                  label={`Nama templat WhatsApp (isi ${k.api_parameters.map((p, i) => `{{${String(i + 1)}}}=${p}`).join(', ')})`}
                  value={form.data.kinds[k.kind].api_template}
                  onChange={(event) => {
                    setKind(k.kind, { api_template: event.target.value });
                  }}
                  error={error(`kinds.${k.kind}.api_template`)}
                />
              ) : null}
            </fieldset>
          ))}
          <p className="text-muted text-sm">
            Penanda yang tersedia: {placeholders.map((p) => `{${p}}`).join(' ')}.
          </p>

          {form.data.mode === 'api' ? (
            <fieldset className="form-grid">
              <legend className="text-lg font-semibold">WhatsApp Business API</legend>
              <TextField
                label="ID nomor telepon (Phone Number ID)"
                inputMode="numeric"
                value={form.data.api_phone_number_id}
                onChange={(event) => {
                  form.setData('api_phone_number_id', event.target.value);
                }}
                error={error('api_phone_number_id')}
              />
              <TextField
                label={
                  settings.has_token ? 'Token akses (kosongkan bila tidak diganti)' : 'Token akses'
                }
                type="password"
                autoComplete="off"
                value={form.data.api_token}
                onChange={(event) => {
                  form.setData('api_token', event.target.value);
                }}
                error={error('api_token')}
              />
              <TextField
                label="Kode bahasa templat"
                value={form.data.api_language}
                onChange={(event) => {
                  form.setData('api_language', event.target.value);
                }}
                error={error('api_language')}
              />
              {!settings.api_ready ? (
                <Alert tone="warning">ID nomor telepon dan token belum lengkap.</Alert>
              ) : null}
            </fieldset>
          ) : null}

          <div>
            <Button type="submit" loading={form.processing}>
              Simpan pengaturan
            </Button>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
