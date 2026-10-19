import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { TextArea } from '@/components/ui/TextArea';
import { TextField } from '@/components/ui/TextField';
import { reminderSettingsSchema } from '@/features/reminders/schemas';
import type { ReminderSettingsValues } from '@/features/reminders/schemas';

interface Props {
  settings: {
    enabled: boolean;
    mode: 'link' | 'api';
    recall_months: number;
    provider: string;
    provider_label: string;
    uses_templates: boolean;
    channel_ready: boolean;
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

/** P2-03: pengaturan isi pengingat (jenis, templat). Saluran pengiriman diatur di Pengaturan WhatsApp. */
export default function Reminders({ settings, kinds, placeholders }: Props) {
  const form = useForm<ReminderSettingsValues>({
    enabled: settings.enabled,
    recall_months: String(settings.recall_months),
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
    form.put('/settings/reminders', { preserveScroll: true });
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
          <Alert tone={settings.mode === 'api' && !settings.channel_ready ? 'warning' : 'info'}>
            Saluran pengiriman: {settings.provider_label}
            {settings.mode === 'api' && !settings.channel_ready
              ? ' (kredensial belum lengkap)'
              : ''}
            .{' '}
            <Link href="/settings/whatsapp" className="underline">
              Ubah di Pengaturan WhatsApp
            </Link>
            .
          </Alert>
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
              {settings.uses_templates ? (
                <TextField
                  label={`Nama templat WhatsApp Resmi (isi ${k.api_parameters.map((p, i) => `{{${String(i + 1)}}}=${p}`).join(', ')})`}
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
