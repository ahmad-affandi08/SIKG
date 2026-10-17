import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { DatePicker } from '@/components/ui/DatePicker';
import { checkDuplicates } from '@/features/patient/api';
import type { DuplicateCandidate } from '@/features/patient/api';
import { buildPatientSchema } from '@/features/patient/schemas';
import type { PatientFormValues } from '@/features/patient/schemas';
import { formatDate } from '@/lib/format';
import { HttpError } from '@/lib/http';
import { todayIso } from '@/lib/date';
import { usePageRequired } from '@/lib/useRequired';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface PatientDto {
  id: string;
  name: string;
  nik: string | null;
  nik_missing_reason: string | null;
  birth_date: string;
  sex: string;
  address: string | null;
  phone: string | null;
  occupation: string | null;
  emergency_name: string | null;
  emergency_relation: string | null;
  emergency_phone: string | null;
  guardian_name: string | null;
  guardian_relation: string | null;
  guardian_phone: string | null;
}

interface Props {
  patient: PatientDto | null;
  consent: { version: string; text: string } | null;
  sexes: SelectOption[];
}

function initial(patient: PatientDto | null): PatientFormValues {
  return {
    name: patient?.name ?? '',
    nik: patient?.nik ?? '',
    nik_missing_reason: patient?.nik_missing_reason ?? '',
    birth_date: patient?.birth_date ?? '',
    sex: patient?.sex ?? '',
    address: patient?.address ?? '',
    phone: patient?.phone ?? '',
    occupation: patient?.occupation ?? '',
    emergency_name: patient?.emergency_name ?? '',
    emergency_relation: patient?.emergency_relation ?? '',
    emergency_phone: patient?.emergency_phone ?? '',
    guardian_name: patient?.guardian_name ?? '',
    guardian_relation: patient?.guardian_relation ?? '',
    guardian_phone: patient?.guardian_phone ?? '',
    consent: false,
  };
}

export default function Form({ patient, consent, sexes }: Props) {
  const editing = patient !== null;
  const timezone = useClinicTimezone();
  const consentRequired = usePageRequired('consent');
  const form = useForm<PatientFormValues & { acknowledge_duplicates: boolean }>({
    ...initial(patient),
    acknowledge_duplicates: false,
  });
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});
  const [duplicates, setDuplicates] = useState<DuplicateCandidate[]>([]);
  const [checking, setChecking] = useState(false);
  const [checkError, setCheckError] = useState<string | null>(null);
  const schema = buildPatientSchema({ requireConsent: !editing });

  const text = (key: keyof PatientFormValues) => ({
    field: key,
    value: String(form.data[key]),
    onChange: (event: { target: { value: string } }) => {
      form.setData(key, event.target.value as never);
    },
    error: clientErrors[key] ?? (form.errors as Record<string, string | undefined>)[key],
  });

  function send(acknowledge: boolean) {
    form.transform((data) => ({ ...data, acknowledge_duplicates: acknowledge }));
    if (editing) {
      form.put(`/patients/${patient.id}`);
    } else {
      form.post('/patients');
    }
  }

  async function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = schema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setClientErrors(next);
      return;
    }
    setClientErrors({});
    setCheckError(null);
    setChecking(true);
    try {
      const found = await checkDuplicates(form.data, patient?.id);
      if (found.length > 0) {
        setDuplicates(found);
        return;
      }
      setDuplicates([]);
      send(false);
    } catch (error) {
      setCheckError(
        error instanceof HttpError ? error.message : 'Pemeriksaan duplikat gagal. Coba lagi.',
      );
    } finally {
      setChecking(false);
    }
  }

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-2xl flex-col gap-4 p-6 lg:px-12 lg:py-10">
        <PageHead title={editing ? 'Ubah data pasien' : 'Pasien baru'} />
        <h1>{editing ? 'Ubah data pasien' : 'Pasien baru'}</h1>

        {duplicates.length > 0 ? (
          <Alert tone="warning">
            <p>Ada pasien yang mungkin sama. Periksa dulu sebelum melanjutkan.</p>
            <ul className="mt-2 list-disc pl-5">
              {duplicates.map((d) => (
                <li key={d.id}>
                  <Link href={`/patients/${d.id}`} className="underline">
                    {d.medical_record_no} - {d.name}
                  </Link>{' '}
                  (lahir {formatDate(d.birth_date)};{' '}
                  {d.reason === 'nik' ? 'NIK sama' : 'nama atau HP mirip'})
                </li>
              ))}
            </ul>
            <div className="mt-3">
              <Button
                loading={form.processing}
                onClick={() => {
                  send(true);
                }}
              >
                Pasien berbeda, tetap simpan
              </Button>
            </div>
          </Alert>
        ) : null}
        {checkError ? <Alert tone="error">{checkError}</Alert> : null}

        <form onSubmit={(event) => void submit(event)} noValidate className="flex flex-col gap-4">
          <TextField label="Nama lengkap" {...text('name')} />
          <TextField label="NIK" inputMode="numeric" autoComplete="off" {...text('nik')} />
          <TextField
            label="Alasan NIK belum ada (isi hanya bila NIK kosong)"
            {...text('nik_missing_reason')}
          />
          <DatePicker
            label="Tanggal lahir"
            field="birth_date"
            value={form.data.birth_date}
            max={todayIso(timezone)}
            clearable={false}
            onChange={(value) => {
              form.setData('birth_date', value);
            }}
            error={clientErrors.birth_date ?? form.errors.birth_date}
          />
          <Select
            label="Jenis kelamin"
            field="sex"
            placeholder="Pilih"
            options={sexes}
            value={form.data.sex}
            onChange={(event) => {
              form.setData('sex', event.target.value);
            }}
            error={clientErrors.sex ?? form.errors.sex}
          />
          <TextField label="Alamat" {...text('address')} />
          <TextField label="Nomor HP atau WhatsApp" inputMode="tel" {...text('phone')} />
          <TextField label="Pekerjaan" {...text('occupation')} />

          <fieldset className="flex flex-col gap-4">
            <legend className="text-lg font-semibold">Kontak darurat</legend>
            <TextField label="Nama kontak darurat" {...text('emergency_name')} />
            <TextField label="Hubungan kontak darurat" {...text('emergency_relation')} />
            <TextField
              label="Nomor HP kontak darurat"
              inputMode="tel"
              {...text('emergency_phone')}
            />
          </fieldset>

          <fieldset className="flex flex-col gap-4">
            <legend className="text-lg font-semibold">Wali atau penanggung jawab</legend>
            <p className="text-muted text-sm">Wajib untuk pasien di bawah 18 tahun.</p>
            <TextField label="Nama wali" {...text('guardian_name')} />
            <TextField label="Hubungan wali dengan pasien" {...text('guardian_relation')} />
            <TextField label="Nomor HP wali" inputMode="tel" {...text('guardian_phone')} />
          </fieldset>

          {consent ? (
            <fieldset className="flex flex-col gap-2">
              <legend className="text-lg font-semibold">Persetujuan data pribadi</legend>
              <p className="rounded-control bg-slate-50 p-3 text-sm">{consent.text}</p>
              <label className="min-h-touch flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  aria-required={consentRequired ? true : undefined}
                  checked={form.data.consent}
                  onChange={(event) => {
                    form.setData('consent', event.target.checked);
                  }}
                />
                <span>
                  Pasien telah membaca dan menyetujui (teks versi {consent.version})
                  {consentRequired ? (
                    <span aria-hidden="true" className="text-signal-700 ml-0.5">
                      *
                    </span>
                  ) : null}
                </span>
              </label>
              {clientErrors.consent ? (
                <p role="alert" className="text-danger-600 text-sm">
                  {clientErrors.consent}
                </p>
              ) : null}
            </fieldset>
          ) : null}

          <div className="flex gap-3">
            <Button type="submit" loading={form.processing || checking}>
              {editing ? 'Simpan perubahan' : 'Daftarkan pasien'}
            </Button>
            <Link
              href={editing ? `/patients/${patient.id}` : '/patients'}
              className="btn btn-secondary"
            >
              Batal
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
