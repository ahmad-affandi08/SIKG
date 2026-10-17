import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import {
  anamnesisSchema,
  SOAP_FIELDS,
  soapSchema,
  temperatureToTenths,
  tenthsToTemperature,
} from '@/features/clinical/schemas';
import type { SoapKey } from '@/features/clinical/schemas';
import { formatBytes } from '@/features/clinical/files';
import { formatDateTime, formatRupiah } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

export interface Warning {
  type: string;
  type_label: string;
  name: string;
  severity: string | null;
}

export interface Note {
  id: string;
  type: string;
  section: string | null;
  body: string;
  author: string;
  authored_at: string;
  reason: string | null;
}

export interface EncounterData {
  id: string;
  is_closed: boolean;
  chief_complaint: string | null;
  complaint_history: string | null;
  vitals: {
    systolic: number | null;
    diastolic: number | null;
    pulse: number | null;
    temperature_tenths: number | null;
  };
}

export function collect(issues: readonly { path: PropertyKey[]; message: string }[]) {
  const out: Record<string, string> = {};
  for (const issue of issues) {
    out[String(issue.path[0])] ??= issue.message;
  }
  return out;
}

const SEVERITY: Record<string, string> = { mild: 'ringan', moderate: 'sedang', severe: 'berat' };

/** Banner alergi dan penyakit sistemik: tampil di setiap layar pemeriksaan (PAS-04). */
export function MedicalWarnings({ warnings }: { warnings: Warning[] }) {
  if (warnings.length === 0) {
    return null;
  }
  return (
    <Alert tone="warning">
      <span className="font-semibold">Peringatan medis:</span>{' '}
      {warnings
        .map(
          (w) =>
            `${w.type_label} ${w.name}${w.severity ? ` (${SEVERITY[w.severity] ?? w.severity})` : ''}`,
        )
        .join('; ')}
    </Alert>
  );
}

export function AnamnesisPanel({
  encounter,
  canWrite,
}: {
  encounter: EncounterData;
  canWrite: boolean;
}) {
  const [values, setValues] = useState({
    chief_complaint: encounter.chief_complaint ?? '',
    complaint_history: encounter.complaint_history ?? '',
    systolic: encounter.vitals.systolic?.toString() ?? '',
    diastolic: encounter.vitals.diastolic?.toString() ?? '',
    pulse: encounter.vitals.pulse?.toString() ?? '',
    temperature: tenthsToTemperature(encounter.vitals.temperature_tenths),
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const set = (key: keyof typeof values, value: string) => {
    setValues((v) => ({ ...v, [key]: value }));
  };

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = anamnesisSchema.safeParse(values);
    if (!result.success) {
      setErrors(collect(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.put(
      `/encounters/${encounter.id}/anamnesis`,
      {
        chief_complaint: values.chief_complaint,
        complaint_history: values.complaint_history,
        systolic: values.systolic === '' ? null : Number(values.systolic),
        diastolic: values.diastolic === '' ? null : Number(values.diastolic),
        pulse: values.pulse === '' ? null : Number(values.pulse),
        temperature_tenths: temperatureToTenths(values.temperature),
      },
      {
        preserveScroll: true,
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
    <form
      onSubmit={submit}
      noValidate
      aria-label="Anamnesis"
      className="flex max-w-2xl flex-col gap-3"
    >
      <fieldset disabled={!canWrite} className="flex flex-col gap-3">
        <TextField
          label="Keluhan utama"
          value={values.chief_complaint}
          onChange={(e) => {
            set('chief_complaint', e.target.value);
          }}
          error={errors.chief_complaint}
        />
        <TextField
          label="Riwayat keluhan"
          value={values.complaint_history}
          onChange={(e) => {
            set('complaint_history', e.target.value);
          }}
          error={errors.complaint_history}
        />
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <TextField
            label="Sistolik (mmHg)"
            inputMode="numeric"
            value={values.systolic}
            onChange={(e) => {
              set('systolic', e.target.value);
            }}
            error={errors.systolic}
          />
          <TextField
            label="Diastolik (mmHg)"
            inputMode="numeric"
            value={values.diastolic}
            onChange={(e) => {
              set('diastolic', e.target.value);
            }}
            error={errors.diastolic}
          />
          <TextField
            label="Nadi (x/menit)"
            inputMode="numeric"
            value={values.pulse}
            onChange={(e) => {
              set('pulse', e.target.value);
            }}
            error={errors.pulse}
          />
          <TextField
            label="Suhu (derajat C)"
            inputMode="decimal"
            value={values.temperature}
            onChange={(e) => {
              set('temperature', e.target.value);
            }}
            error={errors.temperature}
          />
        </div>
        {errors.domain ? (
          <p role="alert" className="text-danger-600 text-sm">
            {errors.domain}
          </p>
        ) : null}
      </fieldset>
      {canWrite ? (
        <div>
          <Button type="submit" loading={saving}>
            Simpan anamnesis
          </Button>
        </div>
      ) : null}
    </form>
  );
}

export function SoapPanel({
  encounter,
  notes,
  canWrite,
}: {
  encounter: EncounterData;
  notes: Note[];
  canWrite: boolean;
}) {
  const bodyOf = (type: string, section: string | null) =>
    notes.find((n) => n.type === type && n.section === section);
  const initial: Record<SoapKey, Note | undefined> = {
    subjective: bodyOf('S', null),
    extra_oral: bodyOf('O', 'extra_oral'),
    intra_oral: bodyOf('O', 'intra_oral'),
    assessment: bodyOf('A', null),
    plan: bodyOf('P', null),
  };
  const [values, setValues] = useState<Record<SoapKey, string>>({
    subjective: initial.subjective?.body ?? '',
    extra_oral: initial.extra_oral?.body ?? '',
    intra_oral: initial.intra_oral?.body ?? '',
    assessment: initial.assessment?.body ?? '',
    plan: initial.plan?.body ?? '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const timezone = useClinicTimezone();

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = soapSchema.safeParse(values);
    if (!result.success) {
      setErrors(collect(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.put(`/encounters/${encounter.id}/notes`, values, {
      preserveScroll: true,
      onError: (serverErrors) => {
        setErrors(serverErrors);
      },
      onFinish: () => {
        setSaving(false);
      },
    });
  }

  return (
    <form
      onSubmit={submit}
      noValidate
      aria-label="Catatan SOAP"
      className="flex max-w-2xl flex-col gap-4"
    >
      <fieldset disabled={!canWrite} className="flex flex-col gap-4">
        {SOAP_FIELDS.map((field) => (
          <div key={field.key} className="flex flex-col gap-1">
            <label htmlFor={`soap-${field.key}`} className="text-sm font-semibold">
              {field.label}
            </label>
            <textarea
              id={`soap-${field.key}`}
              rows={3}
              value={values[field.key]}
              onChange={(event) => {
                setValues((v) => ({ ...v, [field.key]: event.target.value }));
              }}
              aria-invalid={errors[field.key] ? true : undefined}
              className="field py-2"
            />
            {initial[field.key] ? (
              <p className="text-muted text-xs">
                Ditulis {initial[field.key]?.author},{' '}
                {formatDateTime(initial[field.key]?.authored_at ?? '', timezone)}
              </p>
            ) : null}
            {errors[field.key] ? (
              <p role="alert" className="text-danger-600 text-sm">
                {errors[field.key]}
              </p>
            ) : null}
          </div>
        ))}
        {errors.domain ? (
          <p role="alert" className="text-danger-600 text-sm">
            {errors.domain}
          </p>
        ) : null}
      </fieldset>
      {canWrite ? (
        <div>
          <Button type="submit" loading={saving}>
            Simpan catatan
          </Button>
        </div>
      ) : null}
    </form>
  );
}

export interface DiagnosisEntry {
  id: string;
  code: string;
  name: string;
  tooth_fdi: number | null;
  notes: string | null;
}

function AddLink({ href, children }: { href: string; children: string }) {
  return (
    <div>
      <Link href={href} className="btn btn-primary">
        {children}
      </Link>
    </div>
  );
}

export function DiagnosisPanel({
  encounterId,
  entries,
  canEdit,
}: {
  encounterId: string;
  entries: DiagnosisEntry[];
  canEdit: boolean;
}) {
  return (
    <div className="flex max-w-3xl flex-col gap-6">
      {canEdit ? (
        <AddLink href={`/encounters/${encounterId}/diagnoses/create`}>Tambah diagnosa</AddLink>
      ) : null}
      {entries.length === 0 ? <p className="text-muted text-sm">Belum ada diagnosa.</p> : null}
      {entries.length > 0 ? (
        <table>
          <caption className="sr-only">Diagnosa pada kunjungan</caption>
          <thead>
            <tr>
              <th scope="col">Kode</th>
              <th scope="col">Diagnosa</th>
              <th scope="col">Gigi</th>
              <th scope="col">Catatan</th>
              <th scope="col">
                <span className="sr-only">Aksi</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {entries.map((entry) => (
              <tr key={entry.id}>
                <td className="font-medium">{entry.code}</td>
                <td>{entry.name}</td>
                <td>{entry.tooth_fdi ?? '-'}</td>
                <td className="text-muted">{entry.notes ?? '-'}</td>
                <td>
                  {canEdit ? (
                    <ConfirmAction
                      label={`Hapus diagnosa ${entry.code}`}
                      message="Diagnosa dihapus dari kunjungan; jejaknya tetap tersimpan di audit."
                      confirmLabel="Ya, hapus"
                      onConfirm={() => {
                        router.delete(`/encounters/${encounterId}/diagnoses/${entry.id}`, {
                          preserveScroll: true,
                        });
                      }}
                    />
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      ) : null}
    </div>
  );
}

export interface ProcedureEntry {
  id: string;
  code: string;
  name: string;
  tooth_fdi: number | null;
  surfaces: string | null;
  jaw: string | null;
  quantity: number;
  unit_price_amount: number;
  subtotal_amount: number;
  notes: string | null;
}

export function ProcedurePanel({
  encounterId,
  entries,
  jaws,
  canEdit,
}: {
  encounterId: string;
  entries: ProcedureEntry[];
  jaws: SelectOption[];
  canEdit: boolean;
}) {
  const total = entries.reduce((sum, entry) => sum + entry.subtotal_amount, 0);

  return (
    <div className="flex max-w-4xl flex-col gap-6">
      {canEdit ? (
        <AddLink href={`/encounters/${encounterId}/procedures/create`}>Tambah tindakan</AddLink>
      ) : null}
      {entries.length === 0 ? <p className="text-muted text-sm">Belum ada tindakan.</p> : null}
      {entries.length > 0 ? (
        <table>
          <caption className="sr-only">Tindakan pada kunjungan</caption>
          <thead>
            <tr>
              <th scope="col">Tindakan</th>
              <th scope="col">Sasaran</th>
              <th scope="col">Jml</th>
              <th scope="col">Tarif</th>
              <th scope="col">Subtotal</th>
              <th scope="col">
                <span className="sr-only">Aksi</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {entries.map((entry) => (
              <tr key={entry.id} className="align-top">
                <td>
                  <div className="font-medium">{entry.code}</div>
                  <div className="text-muted">{entry.name}</div>
                  {entry.notes ? <div className="text-muted">{entry.notes}</div> : null}
                </td>
                <td>
                  {[
                    entry.tooth_fdi ? `gigi ${String(entry.tooth_fdi)}` : null,
                    entry.surfaces ? `permukaan ${entry.surfaces}` : null,
                    entry.jaw
                      ? (jaws.find((j) => j.value === entry.jaw)?.label ?? entry.jaw)
                      : null,
                  ]
                    .filter(Boolean)
                    .join(', ') || '-'}
                </td>
                <td>{entry.quantity}</td>
                <td>{formatRupiah(entry.unit_price_amount)}</td>
                <td>{formatRupiah(entry.subtotal_amount)}</td>
                <td>
                  {canEdit ? (
                    <ConfirmAction
                      label={`Hapus ${entry.code}`}
                      message="Tindakan dihapus dari kunjungan dan dari draft tagihan."
                      confirmLabel="Ya, hapus"
                      onConfirm={() => {
                        router.delete(`/encounters/${encounterId}/procedures/${entry.id}`, {
                          preserveScroll: true,
                        });
                      }}
                    />
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr className="font-semibold">
              <td colSpan={4}>Total tindakan</td>
              <td colSpan={2}>{formatRupiah(total)}</td>
            </tr>
          </tfoot>
        </table>
      ) : null}
    </div>
  );
}

export function AddendumPanel({
  encounterId,
  addenda,
  canAdd,
}: {
  encounterId: string;
  addenda: Note[];
  canAdd: boolean;
}) {
  const timezone = useClinicTimezone();

  return (
    <section aria-label="Addendum" className="flex max-w-3xl flex-col gap-4">
      <h3>Addendum</h3>
      {canAdd ? (
        <AddLink href={`/encounters/${encounterId}/addenda/create`}>Tambah addendum</AddLink>
      ) : null}
      {addenda.length === 0 ? <p className="text-muted text-sm">Belum ada addendum.</p> : null}
      <ul className="flex flex-col">
        {addenda.map((note) => (
          <li key={note.id} className="border-b border-[#ddd] py-3">
            <p className="text-sm">{note.body}</p>
            <p className="text-muted text-xs">
              {note.author}, {formatDateTime(note.authored_at, timezone)}. Alasan: {note.reason}
            </p>
          </li>
        ))}
      </ul>
    </section>
  );
}

export interface FileEntry {
  id: string;
  kind: string;
  kind_label: string;
  tooth_fdi: number | null;
  mime: string;
  size: number;
  caption: string | null;
  uploaded_at: string;
  uploaded_by: string;
}

export function FilesPanel({
  encounterId,
  files,
  canEdit,
}: {
  encounterId: string;
  files: FileEntry[];
  canEdit: boolean;
}) {
  const timezone = useClinicTimezone();

  return (
    <div className="flex max-w-3xl flex-col gap-6">
      {canEdit ? (
        <AddLink href={`/encounters/${encounterId}/files/create`}>Unggah berkas</AddLink>
      ) : null}
      {files.length === 0 ? <p className="text-muted text-sm">Belum ada berkas.</p> : null}
      {files.length > 0 ? (
        <table>
          <caption className="sr-only">Berkas pada kunjungan</caption>
          <thead>
            <tr>
              <th scope="col">Jenis</th>
              <th scope="col">Berkas</th>
              <th scope="col">Gigi</th>
              <th scope="col">Unggahan</th>
              <th scope="col">
                <span className="sr-only">Aksi</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {files.map((f) => (
              <tr key={f.id}>
                <td>
                  <Badge>{f.kind_label}</Badge>
                </td>
                <td>
                  <a
                    href={`/clinical-files/${f.id}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-ink underline"
                  >
                    {f.caption ?? 'Buka berkas'}
                  </a>
                </td>
                <td>{f.tooth_fdi ?? '-'}</td>
                <td className="text-muted text-xs">
                  {formatBytes(f.size)}, {f.uploaded_by}, {formatDateTime(f.uploaded_at, timezone)}
                </td>
                <td>
                  {canEdit ? (
                    <ConfirmAction
                      label={`Lepas ${f.caption ?? f.kind_label}`}
                      message="Berkas dilepas dari kunjungan; arsip tetap tersimpan."
                      confirmLabel="Ya, lepas"
                      onConfirm={() => {
                        router.delete(`/encounters/${encounterId}/files/${f.id}`, {
                          preserveScroll: true,
                        });
                      }}
                    />
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      ) : null}
    </div>
  );
}
