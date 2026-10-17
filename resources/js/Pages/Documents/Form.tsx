import { Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { TextArea } from '@/components/ui/TextArea';
import { TextField } from '@/components/ui/TextField';
import type { ProcedureOption } from '@/features/clinical/api';
import { ProcedurePicker } from '@/features/clinical/components/ProcedurePicker';
import {
  documentFormSchema,
  renderTemplate,
  restSentence,
  toDisplayDate,
  unresolvedPlaceholders,
} from '@/features/clinical/documents';
import type { DocumentKind } from '@/features/clinical/documents';

interface Props {
  type: { value: DocumentKind; label: string };
  patient: {
    id: string;
    name: string;
    medical_record_no: string;
    birth_date: string | null;
    age: number | null;
  };
  clinic_name: string;
  today: string;
  templates: { id: string; name: string; procedure_id: string | null; body: string }[];
  doctors: { id: string; name: string; sip: string | null }[];
  encounters: {
    id: string;
    date: string;
    doctor_id: string;
    doctor_name: string;
    diagnoses: string;
  }[];
}

/**
 * RM-09 dan RM-10: menyiapkan dokumen dari templat. Penanda terisi langsung di layar dari data pasien dan isian di bawah;
 * teks akhir boleh disunting sebelum diterbitkan dan dicetak untuk tanda tangan basah.
 */
export default function Form({
  type,
  patient,
  clinic_name: clinic,
  today,
  templates,
  doctors,
  encounters,
}: Props) {
  const [templateId, setTemplateId] = useState(templates[0]?.id ?? '');
  const [encounterId, setEncounterId] = useState('');
  const [doctorId, setDoctorId] = useState(doctors[0]?.id ?? '');
  const [procedure, setProcedure] = useState<ProcedureOption | null>(null);
  const [purpose, setPurpose] = useState('');
  const [restDays, setRestDays] = useState('');
  const [destination, setDestination] = useState('');
  const [reason, setReason] = useState('');
  const [complaint, setComplaint] = useState('');
  const [therapy, setTherapy] = useState('');
  const [override, setOverride] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  const template = templates.find((t) => t.id === templateId) ?? templates[0];
  const encounter = encounters.find((e) => e.id === encounterId);
  const doctor = doctors.find((d) => d.id === doctorId);

  const rendered = useMemo(
    () =>
      renderTemplate(template?.body ?? '', {
        pasien: patient.name,
        no_rm: patient.medical_record_no,
        tanggal_lahir: patient.birth_date ? toDisplayDate(patient.birth_date) : '',
        umur: patient.age === null ? '' : String(patient.age),
        klinik: clinic,
        tanggal: toDisplayDate(today),
        dokter: doctor?.name ?? '',
        sip: doctor?.sip ?? '-',
        tanggal_kunjungan: toDisplayDate(encounter?.date ?? today),
        diagnosa: encounter?.diagnoses ?? '',
        tindakan: procedure?.name ?? '',
        keperluan: purpose,
        istirahat: restSentence(restDays),
        tujuan: destination,
        alasan: reason,
        keluhan: complaint,
        terapi: therapy,
      }),
    [
      template,
      patient,
      clinic,
      today,
      doctor,
      encounter,
      procedure,
      purpose,
      restDays,
      destination,
      reason,
      complaint,
      therapy,
    ],
  );
  const body = override ?? rendered;
  const left = unresolvedPlaceholders(body);
  const back = `/patients/${patient.id}/documents`;

  function pickEncounter(id: string) {
    setEncounterId(id);
    const chosen = encounters.find((e) => e.id === id);
    if (chosen) {
      setDoctorId(chosen.doctor_id);
    }
  }

  function pickProcedure(next: ProcedureOption | null) {
    setProcedure(next);
    const match = next ? templates.find((t) => t.procedure_id === next.id) : undefined;
    if (match) {
      setTemplateId(match.id);
    }
  }

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = documentFormSchema.safeParse({ doctor_id: doctorId, body });
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
      `/patients/${patient.id}/documents`,
      {
        type: type.value,
        template_id: templateId === '' ? null : templateId,
        encounter_id: encounterId === '' ? null : encounterId,
        doctor_id: doctorId,
        body: result.data.body,
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
        <PageHead title={type.label} />
        <PageHeader
          title={type.label}
          back={{ href: back, label: 'dokumen klinis' }}
          description={`${patient.name} (${patient.medical_record_no})`}
        />
        <form onSubmit={submit} noValidate aria-label={type.label} className="form-grid">
          <Select
            label="Templat"
            options={templates.map((t) => ({ value: t.id, label: t.name }))}
            value={templateId}
            onChange={(event) => {
              setTemplateId(event.target.value);
              setOverride(null);
            }}
          />
          <Select
            field="doctor_id"
            label="Dokter"
            placeholder="Pilih dokter"
            options={doctors.map((d) => ({ value: d.id, label: d.name }))}
            value={doctorId}
            error={errors.doctor_id}
            onChange={(event) => {
              setDoctorId(event.target.value);
            }}
          />
          <Select
            label="Kunjungan (opsional, mengisi diagnosa dan tanggal)"
            placeholder="Tanpa kunjungan"
            options={encounters.map((e) => ({
              value: e.id,
              label: `${toDisplayDate(e.date)} - ${e.doctor_name}`,
            }))}
            value={encounterId}
            onChange={(event) => {
              pickEncounter(event.target.value);
            }}
          />

          {type.value === 'consent' ? (
            <ProcedurePicker selected={procedure} onSelect={pickProcedure} />
          ) : null}
          {type.value === 'certificate' ? (
            <>
              <TextField
                label="Keperluan"
                required
                value={purpose}
                onChange={(e) => {
                  setPurpose(e.target.value);
                }}
              />
              <TextField
                label="Lama istirahat (hari, kosongkan bila tidak perlu)"
                inputMode="numeric"
                value={restDays}
                onChange={(e) => {
                  setRestDays(e.target.value);
                }}
              />
            </>
          ) : null}
          {type.value === 'referral' ? (
            <>
              <TextField
                label="Tujuan rujukan"
                required
                value={destination}
                onChange={(e) => {
                  setDestination(e.target.value);
                }}
              />
              <TextField
                label="Keluhan dan riwayat"
                required
                value={complaint}
                onChange={(e) => {
                  setComplaint(e.target.value);
                }}
              />
              <TextField
                label="Terapi atau tindakan yang telah diberikan"
                required
                value={therapy}
                onChange={(e) => {
                  setTherapy(e.target.value);
                }}
              />
              <TextField
                label="Alasan rujukan"
                required
                value={reason}
                onChange={(e) => {
                  setReason(e.target.value);
                }}
              />
            </>
          ) : null}

          <TextArea
            field="body"
            label="Isi dokumen (dapat disunting)"
            rows={16}
            value={body}
            error={errors.body}
            onChange={(event) => {
              setOverride(event.target.value);
            }}
          />
          {override !== null ? (
            <p className="text-muted text-sm">
              Teks sudah Anda sunting, jadi isian di atas tidak lagi mengubahnya.{' '}
              <button
                type="button"
                className="text-brand-700 underline"
                onClick={() => {
                  setOverride(null);
                }}
              >
                Isi ulang dari templat
              </button>
            </p>
          ) : null}
          {left.length > 0 ? (
            <Alert tone="warning">
              Belum terisi: {left.join(', ')}. Lengkapi isian di atas atau sunting teksnya.
            </Alert>
          ) : null}
          {errors.domain ? <Alert tone="error">{errors.domain}</Alert> : null}

          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={saving} disabled={left.length > 0}>
              Terbitkan dokumen
            </Button>
            <Link href={back} className="btn btn-secondary">
              Batal
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
