import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import type { ProcedureOption } from '@/features/clinical/api';
import { DiagnosisPicker } from '@/features/clinical/components/DiagnosisPicker';
import { ProcedurePicker } from '@/features/clinical/components/ProcedurePicker';
import {
  addendumSchema,
  anamnesisSchema,
  diagnosisEntrySchema,
  procedureEntrySchema,
  SOAP_FIELDS,
  soapSchema,
  temperatureToTenths,
  tenthsToTemperature,
} from '@/features/clinical/schemas';
import type { SoapKey } from '@/features/clinical/schemas';
import { FILE_KINDS, fileIssue, fileMetaSchema, formatBytes } from '@/features/clinical/files';
import { isValidFdi, SURFACES } from '@/features/clinical/tooth';
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
            <label htmlFor={`soap-${field.key}`} className="text-sm font-medium text-slate-700">
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
              <p className="text-xs text-slate-600">
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

export function DiagnosisPanel({
  encounterId,
  entries,
  canEdit,
}: {
  encounterId: string;
  entries: DiagnosisEntry[];
  canEdit: boolean;
}) {
  const [picked, setPicked] = useState<{ id: string; code: string; name: string } | null>(null);
  const [tooth, setTooth] = useState('');
  const [notes, setNotes] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = { diagnosis_id: picked?.id ?? '', tooth_fdi: tooth, notes };
    const result = diagnosisEntrySchema.safeParse(values);
    if (!result.success) {
      setErrors(collect(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      `/encounters/${encounterId}/diagnoses`,
      { diagnosis_id: values.diagnosis_id, tooth_fdi: tooth === '' ? null : Number(tooth), notes },
      {
        preserveScroll: true,
        onSuccess: () => {
          setPicked(null);
          setTooth('');
          setNotes('');
        },
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
    <div className="flex max-w-2xl flex-col gap-4">
      {entries.length === 0 ? <p className="text-sm text-slate-600">Belum ada diagnosa.</p> : null}
      <ul className="flex flex-col gap-2">
        {entries.map((entry) => (
          <li key={entry.id} className="card flex flex-wrap items-center gap-2 p-2">
            <Badge>{entry.code}</Badge>
            <span className="text-sm font-medium">{entry.name}</span>
            {entry.tooth_fdi ? <span className="text-sm">gigi {entry.tooth_fdi}</span> : null}
            {entry.notes ? <span className="text-sm text-slate-600">{entry.notes}</span> : null}
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
          </li>
        ))}
      </ul>

      {canEdit ? (
        <form
          onSubmit={submit}
          noValidate
          aria-label="Tambah diagnosa"
          className="card flex flex-col gap-3 p-3"
        >
          {picked ? (
            <div className="flex items-center gap-3">
              <span className="text-sm">
                <span className="font-medium">{picked.code}</span> {picked.name}
              </span>
              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => {
                  setPicked(null);
                }}
              >
                Ganti
              </button>
            </div>
          ) : (
            <DiagnosisPicker onSelect={setPicked} />
          )}
          {errors.diagnosis_id ? (
            <p role="alert" className="text-danger-600 text-sm">
              {errors.diagnosis_id}
            </p>
          ) : null}
          <TextField
            label="Nomor gigi (opsional, FDI)"
            inputMode="numeric"
            value={tooth}
            onChange={(e) => {
              setTooth(e.target.value);
            }}
            error={errors.tooth_fdi}
          />
          <TextField
            label="Catatan"
            value={notes}
            onChange={(e) => {
              setNotes(e.target.value);
            }}
            error={errors.notes}
          />
          {errors.domain ? (
            <p role="alert" className="text-danger-600 text-sm">
              {errors.domain}
            </p>
          ) : null}
          <div>
            <Button type="submit" loading={saving}>
              Tambah diagnosa
            </Button>
          </div>
        </form>
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
  const [procedure, setProcedure] = useState<ProcedureOption | null>(null);
  const [tooth, setTooth] = useState('');
  const [surfaces, setSurfaces] = useState<string[]>([]);
  const [jaw, setJaw] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [notes, setNotes] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const scope = procedure?.scope ?? null;
  const total = entries.reduce((sum, entry) => sum + entry.subtotal_amount, 0);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = {
      procedure_id: procedure?.id ?? '',
      tooth_fdi: tooth,
      surfaces,
      jaw,
      quantity,
      notes,
    };
    const result = procedureEntrySchema(scope).safeParse(values);
    if (!result.success) {
      setErrors(collect(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      `/encounters/${encounterId}/procedures`,
      {
        procedure_id: values.procedure_id,
        tooth_fdi: scope === 'tooth' || scope === 'surface' ? Number(tooth) : null,
        surfaces: scope === 'surface' ? surfaces : [],
        jaw: scope === 'jaw' ? jaw : null,
        quantity: result.data.quantity,
        notes,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setProcedure(null);
          setTooth('');
          setSurfaces([]);
          setJaw('');
          setQuantity('1');
          setNotes('');
        },
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
    <div className="flex max-w-3xl flex-col gap-4">
      {entries.length === 0 ? <p className="text-sm text-slate-600">Belum ada tindakan.</p> : null}
      {entries.length > 0 ? (
        <table className="w-full text-left text-sm">
          <caption className="sr-only">Tindakan pada kunjungan</caption>
          <thead>
            <tr>
              <th scope="col" className="py-2">
                Tindakan
              </th>
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
              <tr key={entry.id} className="border-t border-slate-200 align-top">
                <td className="py-2 pr-3">
                  <div className="font-medium">{entry.code}</div>
                  <div className="text-slate-600">{entry.name}</div>
                  {entry.notes ? <div className="text-slate-600">{entry.notes}</div> : null}
                </td>
                <td className="pr-3">
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
                <td className="pr-3">{entry.quantity}</td>
                <td className="pr-3">{formatRupiah(entry.unit_price_amount)}</td>
                <td className="pr-3">{formatRupiah(entry.subtotal_amount)}</td>
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
            <tr className="border-t border-slate-300 font-semibold">
              <td colSpan={4} className="py-2">
                Total tindakan
              </td>
              <td colSpan={2}>{formatRupiah(total)}</td>
            </tr>
          </tfoot>
        </table>
      ) : null}

      {canEdit ? (
        <form
          onSubmit={submit}
          noValidate
          aria-label="Tambah tindakan"
          className="card flex flex-col gap-3 p-3"
        >
          <ProcedurePicker
            selected={procedure}
            onSelect={setProcedure}
            error={errors.procedure_id}
          />
          {scope === 'tooth' || scope === 'surface' ? (
            <TextField
              label="Nomor gigi (FDI)"
              inputMode="numeric"
              value={tooth}
              onChange={(e) => {
                setTooth(e.target.value);
              }}
              error={errors.tooth_fdi}
            />
          ) : null}
          {scope === 'surface' ? (
            <fieldset className="flex flex-col gap-1">
              <legend className="text-sm font-medium">Permukaan</legend>
              <div className="flex flex-wrap gap-3">
                {SURFACES.map((surface) => (
                  <label
                    key={surface.value}
                    className="min-h-touch flex items-center gap-2 text-sm"
                  >
                    <input
                      type="checkbox"
                      checked={surfaces.includes(surface.value)}
                      onChange={(event) => {
                        setSurfaces((current) =>
                          event.target.checked
                            ? [...current, surface.value]
                            : current.filter((s) => s !== surface.value),
                        );
                      }}
                    />
                    {surface.label}
                  </label>
                ))}
              </div>
              {errors.surfaces ? (
                <p role="alert" className="text-danger-600 text-sm">
                  {errors.surfaces}
                </p>
              ) : null}
            </fieldset>
          ) : null}
          {scope === 'jaw' ? (
            <Select
              label="Rahang"
              placeholder="Pilih rahang"
              options={jaws}
              value={jaw}
              onChange={(e) => {
                setJaw(e.target.value);
              }}
              error={errors.jaw}
            />
          ) : null}
          <TextField
            label="Jumlah"
            inputMode="numeric"
            value={quantity}
            onChange={(e) => {
              setQuantity(e.target.value);
            }}
            error={errors.quantity}
          />
          <TextField
            label="Catatan"
            value={notes}
            onChange={(e) => {
              setNotes(e.target.value);
            }}
            error={errors.notes}
          />
          {errors.domain ? (
            <p role="alert" className="text-danger-600 text-sm">
              {errors.domain}
            </p>
          ) : null}
          <div>
            <Button type="submit" loading={saving}>
              Tambah tindakan
            </Button>
          </div>
        </form>
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
  const [values, setValues] = useState({ body: '', reason: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const timezone = useClinicTimezone();

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = addendumSchema.safeParse(values);
    if (!result.success) {
      setErrors(collect(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(`/encounters/${encounterId}/addenda`, values, {
      preserveScroll: true,
      onSuccess: () => {
        setValues({ body: '', reason: '' });
      },
      onError: (serverErrors) => {
        setErrors(serverErrors);
      },
      onFinish: () => {
        setSaving(false);
      },
    });
  }

  return (
    <section aria-label="Addendum" className="flex max-w-2xl flex-col gap-3">
      <h3 className="text-base font-semibold">Addendum</h3>
      {addenda.length === 0 ? <p className="text-sm text-slate-600">Belum ada addendum.</p> : null}
      <ul className="flex flex-col gap-2">
        {addenda.map((note) => (
          <li key={note.id} className="card p-2">
            <p className="text-sm">{note.body}</p>
            <p className="text-xs text-slate-600">
              {note.author}, {formatDateTime(note.authored_at, timezone)}. Alasan: {note.reason}
            </p>
          </li>
        ))}
      </ul>
      {canAdd ? (
        <form
          onSubmit={submit}
          noValidate
          aria-label="Tambah addendum"
          className="card flex flex-col gap-3 p-3"
        >
          <div className="flex flex-col gap-1">
            <label htmlFor="addendum-body" className="text-sm font-medium">
              Isi addendum
            </label>
            <textarea
              id="addendum-body"
              rows={3}
              value={values.body}
              onChange={(event) => {
                setValues((v) => ({ ...v, body: event.target.value }));
              }}
              className="field py-2"
            />
            {errors.body ? (
              <p role="alert" className="text-danger-600 text-sm">
                {errors.body}
              </p>
            ) : null}
          </div>
          <TextField
            label="Alasan addendum"
            value={values.reason}
            onChange={(e) => {
              setValues((v) => ({ ...v, reason: e.target.value }));
            }}
            error={errors.reason}
          />
          {errors.domain ? (
            <p role="alert" className="text-danger-600 text-sm">
              {errors.domain}
            </p>
          ) : null}
          <div>
            <Button type="submit" loading={saving}>
              Tambah addendum
            </Button>
          </div>
        </form>
      ) : null}
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
  const [file, setFile] = useState<File | null>(null);
  const [kind, setKind] = useState('xray');
  const [tooth, setTooth] = useState('');
  const [caption, setCaption] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const timezone = useClinicTimezone();

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const issue = fileIssue(file);
    const meta = fileMetaSchema.safeParse({ kind, tooth_fdi: tooth, caption });
    const next: Record<string, string> = meta.success ? {} : collect(meta.error.issues);
    if (issue) {
      next.file = issue;
    }
    if (tooth !== '' && !isValidFdi(Number(tooth))) {
      next.tooth_fdi = 'Nomor gigi tidak valid menurut FDI.';
    }
    if (Object.keys(next).length > 0) {
      setErrors(next);
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      `/encounters/${encounterId}/files`,
      { file, kind, tooth_fdi: tooth === '' ? null : Number(tooth), caption },
      {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
          setFile(null);
          setTooth('');
          setCaption('');
        },
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
    <div className="flex max-w-3xl flex-col gap-4">
      {files.length === 0 ? <p className="text-sm text-slate-600">Belum ada berkas.</p> : null}
      <ul className="flex flex-col gap-2">
        {files.map((f) => (
          <li key={f.id} className="card flex flex-wrap items-center gap-2 p-2">
            <Badge>{f.kind_label}</Badge>
            <a
              href={`/clinical-files/${f.id}`}
              target="_blank"
              rel="noopener noreferrer"
              className="text-brand-700 text-sm underline"
            >
              {f.caption ?? 'Buka berkas'}
            </a>
            {f.tooth_fdi ? <span className="text-sm">gigi {f.tooth_fdi}</span> : null}
            <span className="text-xs text-slate-600">
              {formatBytes(f.size)}, {f.uploaded_by}, {formatDateTime(f.uploaded_at, timezone)}
            </span>
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
          </li>
        ))}
      </ul>

      {canEdit ? (
        <form
          onSubmit={submit}
          noValidate
          aria-label="Unggah berkas"
          className="card flex flex-col gap-3 p-3"
        >
          <div className="flex flex-col gap-1">
            <label htmlFor="clinical-file" className="text-sm font-medium">
              Berkas (JPG, PNG, atau PDF, maksimal 10 MB)
            </label>
            <input
              id="clinical-file"
              type="file"
              accept="image/jpeg,image/png,application/pdf"
              onChange={(event) => {
                setFile(event.target.files?.[0] ?? null);
              }}
            />
            {errors.file ? (
              <p role="alert" className="text-danger-600 text-sm">
                {errors.file}
              </p>
            ) : null}
          </div>
          <Select
            label="Jenis"
            options={FILE_KINDS.map((k) => ({ value: k.value, label: k.label }))}
            value={kind}
            onChange={(event) => {
              setKind(event.target.value);
            }}
            error={errors.kind}
          />
          <TextField
            label="Nomor gigi (opsional, FDI)"
            inputMode="numeric"
            value={tooth}
            onChange={(e) => {
              setTooth(e.target.value);
            }}
            error={errors.tooth_fdi}
          />
          <TextField
            label="Keterangan"
            value={caption}
            onChange={(e) => {
              setCaption(e.target.value);
            }}
            error={errors.caption}
          />
          {errors.domain ? (
            <p role="alert" className="text-danger-600 text-sm">
              {errors.domain}
            </p>
          ) : null}
          <div>
            <Button type="submit" loading={saving}>
              Unggah berkas
            </Button>
          </div>
        </form>
      ) : null}
    </div>
  );
}
