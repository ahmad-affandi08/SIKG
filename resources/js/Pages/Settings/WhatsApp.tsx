import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { missingRequired, whatsappSettingsSchema } from '@/features/reminders/whatsapp';
import type { ProviderValue, WhatsAppSettingsValues } from '@/features/reminders/whatsapp';

interface Provider {
  value: ProviderValue;
  label: string;
  description: string;
  official: boolean;
  fields: string[];
  required: string[];
}

interface Props {
  providers: Provider[];
  settings: {
    provider: ProviderValue;
    phone_number_id: string | null;
    base_url: string | null;
    session: string | null;
    language: string;
    staff_template: string | null;
    has_token: boolean;
    ready: boolean;
  };
}

/** Teks isian yang berbeda per penyedia (token punya nama dan bentuk sendiri di tiap penyedia). */
const TOKEN_LABEL: Record<ProviderValue, string> = {
  link: 'Token',
  meta: 'Token akses permanen (Meta)',
  fonnte: 'Token akun Fonnte',
  wablas: 'Token Wablas (atau token.kunci-rahasia)',
  waha: 'Kunci API WAHA (X-Api-Key, kosongkan bila tidak dipakai)',
};

const BASE_URL_HINT: Partial<Record<ProviderValue, string>> = {
  wablas: 'Alamat server akun Wablas Anda, contoh https://tegal.wablas.com',
  waha: 'Alamat server WAHA yang Anda pasang, contoh https://waha.klinik-anda.id',
};

/** Pengaturan saluran WhatsApp: WhatsApp Resmi (Meta) atau penyedia tidak resmi; satu saluran untuk pengingat, kode booking, dan staf. */
export default function WhatsApp({ providers, settings }: Props) {
  const form = useForm<WhatsAppSettingsValues>({
    provider: settings.provider,
    phone_number_id: settings.phone_number_id ?? '',
    token: '',
    base_url: settings.base_url ?? '',
    session: settings.session ?? '',
    language: settings.language,
    staff_template: settings.staff_template ?? '',
  });
  const testForm = useForm({ phone: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});

  const current = providers.find((p) => p.value === form.data.provider) ?? providers[0];
  const fields = current?.fields ?? [];
  // Token lama hanya dipertahankan bila penyedia dan alamat server tidak berubah (server menegakkan aturan yang sama).
  const tokenKept =
    settings.has_token &&
    settings.provider === form.data.provider &&
    (settings.base_url ?? '') === form.data.base_url.trim().replace(/\/+$/, '');
  const error = (key: string): string | undefined =>
    errors[key] ?? (form.errors as Record<string, string | undefined>)[key];

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = whatsappSettingsSchema.safeParse(form.data);
    const next: Record<string, string> = {};
    if (!result.success) {
      for (const issue of result.error.issues) {
        next[issue.path.join('.')] ??= issue.message;
      }
    }
    Object.assign(next, missingRequired(form.data, current?.required ?? [], tokenKept));
    if (Object.keys(next).length > 0) {
      setErrors(next);
      return;
    }
    setErrors({});
    form.put('/settings/whatsapp', {
      preserveScroll: true,
      onSuccess: () => {
        form.setData('token', '');
      },
    });
  }

  function sendTest(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    testForm.post('/settings/whatsapp/test', { preserveScroll: true });
  }

  return (
    <AppLayout>
      <main className="flex max-w-3xl flex-col gap-4 p-3">
        <PageHead title="Pengaturan WhatsApp" />
        <PageHeader
          title="Pengaturan WhatsApp"
          description="Satu saluran untuk pengingat pasien, kode verifikasi booking, dan pemberitahuan staf."
        />

        <form onSubmit={submit} noValidate aria-label="Pengaturan WhatsApp" className="form-grid">
          <fieldset className="flex flex-col gap-2">
            <legend className="text-lg font-semibold">Penyedia WhatsApp</legend>
            {providers.map((provider) => (
              <label
                key={provider.value}
                htmlFor={`provider-${provider.value}`}
                className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 ${
                  form.data.provider === provider.value
                    ? 'border-brand-600 bg-brand-50'
                    : 'border-line-soft'
                }`}
              >
                <input
                  id={`provider-${provider.value}`}
                  aria-label={provider.label}
                  type="radio"
                  name="provider"
                  className="mt-1"
                  value={provider.value}
                  checked={form.data.provider === provider.value}
                  onChange={() => {
                    form.setData('provider', provider.value);
                    setErrors({});
                  }}
                />
                <span className="flex flex-col gap-0.5">
                  <span className="font-semibold">
                    {provider.label}
                    {provider.official ? (
                      <span className="text-brand-800 bg-brand-100 ml-2 rounded-full px-2 py-0.5 text-xs">
                        Resmi
                      </span>
                    ) : null}
                  </span>
                  <span className="text-muted text-sm">{provider.description}</span>
                </span>
              </label>
            ))}
          </fieldset>

          {current && !current.official && current.value !== 'link' ? (
            <Alert tone="warning">
              Penyedia tidak resmi melanggar ketentuan layanan WhatsApp: nomor klinik dapat diblokir
              kapan saja, dan isi pesan melewati server pihak ketiga. Pesan SIKG tidak memuat
              diagnosa atau NIK, tetapi pengingat memuat nama depan, tanggal, jam, dan nama dokter.
              Untuk kepatuhan penuh pakai WhatsApp Resmi.
            </Alert>
          ) : null}

          {fields.length > 0 ? (
            <fieldset className="form-grid">
              <legend className="text-lg font-semibold">Kredensial</legend>
              {fields.includes('phone_number_id') ? (
                <TextField
                  required={current?.required.includes('phone_number_id') ?? false}
                  label="ID nomor telepon (Phone Number ID)"
                  inputMode="numeric"
                  value={form.data.phone_number_id}
                  onChange={(event) => {
                    form.setData('phone_number_id', event.target.value);
                  }}
                  error={error('phone_number_id')}
                />
              ) : null}
              {fields.includes('base_url') ? (
                <TextField
                  required={current?.required.includes('base_url') ?? false}
                  label="Alamat server"
                  placeholder="https://"
                  value={form.data.base_url}
                  onChange={(event) => {
                    form.setData('base_url', event.target.value);
                  }}
                  error={error('base_url')}
                />
              ) : null}
              {fields.includes('base_url') && BASE_URL_HINT[form.data.provider] ? (
                <p className="text-muted text-sm">{BASE_URL_HINT[form.data.provider]}</p>
              ) : null}
              {fields.includes('token') ? (
                <TextField
                  required={(current?.required.includes('token') ?? false) && !tokenKept}
                  label={
                    tokenKept
                      ? `${TOKEN_LABEL[form.data.provider]} (kosongkan bila tidak diganti)`
                      : TOKEN_LABEL[form.data.provider]
                  }
                  type="password"
                  autoComplete="off"
                  value={form.data.token}
                  onChange={(event) => {
                    form.setData('token', event.target.value);
                  }}
                  error={error('token')}
                />
              ) : null}
              {fields.includes('session') ? (
                <TextField
                  label="Nama sesi (kosong = default)"
                  value={form.data.session}
                  onChange={(event) => {
                    form.setData('session', event.target.value);
                  }}
                  error={error('session')}
                />
              ) : null}
              {fields.includes('language') ? (
                <TextField
                  label="Kode bahasa templat"
                  value={form.data.language}
                  onChange={(event) => {
                    form.setData('language', event.target.value);
                  }}
                  error={error('language')}
                />
              ) : null}
              {fields.includes('staff_template') ? (
                <TextField
                  label="Nama templat pemberitahuan staf (2 parameter: {{1}} judul, {{2}} isi)"
                  value={form.data.staff_template}
                  onChange={(event) => {
                    form.setData('staff_template', event.target.value);
                  }}
                  error={error('staff_template')}
                />
              ) : null}
            </fieldset>
          ) : null}

          {current?.value === 'meta' ? (
            <p className="text-muted text-sm">
              Nama templat pengingat pasien diisi di Pengaturan pengingat. Tanpa templat
              pemberitahuan staf, pemberitahuan staf hanya tampil di aplikasi.
            </p>
          ) : null}

          {settings.provider !== 'link' && !settings.ready ? (
            <Alert tone="warning">Kredensial penyedia yang tersimpan belum lengkap.</Alert>
          ) : null}

          <div>
            <Button type="submit" loading={form.processing}>
              Simpan pengaturan
            </Button>
          </div>
        </form>

        {settings.ready ? (
          <form
            onSubmit={sendTest}
            noValidate
            aria-label="Kirim pesan uji"
            className="form-grid border-line-soft border-t pt-4"
          >
            <h2>Kirim pesan uji</h2>
            <p className="text-muted text-sm">
              Mengirim satu pesan uji lewat pengaturan yang sudah tersimpan. Nomor tidak disimpan.
            </p>
            <TextField
              required
              label="Nomor WhatsApp tujuan"
              inputMode="tel"
              placeholder="081234567890"
              value={testForm.data.phone}
              onChange={(event) => {
                testForm.setData('phone', event.target.value);
              }}
              error={testForm.errors.phone}
            />
            <div>
              <Button type="submit" variant="secondary" loading={testForm.processing}>
                Kirim pesan uji
              </Button>
            </div>
          </form>
        ) : null}
      </main>
    </AppLayout>
  );
}
