import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { Badge } from '@/components/ui/Badge';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import {
  AddendumPanel,
  AnamnesisPanel,
  DiagnosisPanel,
  FilesPanel,
  MedicalWarnings,
  ProcedurePanel,
  SoapPanel,
} from '@/features/clinical/components/EncounterPanels';
import type {
  DiagnosisEntry,
  EncounterData,
  FileEntry,
  Note,
  ProcedureEntry,
  Warning,
} from '@/features/clinical/components/EncounterPanels';
import { PrescriptionPanel } from '@/features/pharmacy/components/PrescriptionPanel';
import type { PrescriptionData } from '@/features/pharmacy/components/PrescriptionPanel';
import { OdontogramBoard } from '@/features/clinical/odontogram/OdontogramBoard';
import { formatDateTime } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Props {
  encounter: EncounterData & {
    status_label: string;
    started_at: string;
    closed_at: string | null;
    patient_id: string;
    patient_name: string | null;
    medical_record_no: string | null;
    doctor_name: string | null;
    chair_id: string | null;
    chair_name: string | null;
  };
  notes: Note[];
  addenda: Note[];
  note_history: Note[];
  diagnoses: DiagnosisEntry[];
  procedures: ProcedureEntry[];
  files: FileEntry[];
  prescriptions: PrescriptionData[];
  warnings: Warning[];
  medical_history: Warning[];
  chairs: SelectOption[];
  jaws: SelectOption[];
  can: {
    write: boolean;
    start: boolean;
    close: boolean;
    addendum: boolean;
    prescribe: boolean;
    printPrescription: boolean;
    overrideStock: boolean;
    odontogram: boolean;
  };
}

const TABS = [
  { key: 'anamnesis', label: 'Anamnesis' },
  { key: 'soap', label: 'Catatan SOAP' },
  { key: 'odontogram', label: 'Odontogram' },
  { key: 'diagnosis', label: 'Diagnosa' },
  { key: 'procedures', label: 'Tindakan' },
  { key: 'prescriptions', label: 'Resep' },
  { key: 'files', label: 'Berkas' },
  { key: 'addenda', label: 'Addendum dan riwayat' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

export default function Show(props: Props) {
  const {
    encounter,
    notes,
    addenda,
    note_history: history,
    diagnoses,
    procedures,
    files,
    prescriptions,
    warnings,
    chairs,
    jaws,
    can,
  } = props;
  const [tab, setTab] = useState<TabKey>('anamnesis');
  const [chairId, setChairId] = useState(encounter.chair_id ?? '');
  const timezone = useClinicTimezone();
  const canEdit = can.write && !encounter.is_closed;

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-5xl flex-col gap-4 p-6">
        <Head title="Pemeriksaan" />
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold">{encounter.patient_name ?? 'Pasien'}</h1>
            <p className="text-sm text-slate-600">
              Nomor RM {encounter.medical_record_no ?? '-'} | Dokter {encounter.doctor_name ?? '-'}{' '}
              | Mulai {formatDateTime(encounter.started_at, timezone)}
            </p>
            <p className="text-sm">
              <Link href={`/patients/${encounter.patient_id}`} className="text-brand-700 underline">
                Data pasien
              </Link>
            </p>
          </div>
          <div className="flex flex-wrap items-end gap-2">
            <Badge tone={encounter.is_closed ? 'neutral' : 'warning'}>
              {encounter.status_label}
            </Badge>
            {can.start && !encounter.is_closed ? (
              <>
                <Select
                  label="Kursi"
                  placeholder="Belum ditentukan"
                  options={chairs}
                  value={chairId}
                  onChange={(event) => {
                    setChairId(event.target.value);
                  }}
                />
                <ConfirmAction
                  label="Mulai pemeriksaan"
                  message="Janji ditandai Sedang diperiksa."
                  confirmLabel="Ya, mulai"
                  variant="primary"
                  onConfirm={() => {
                    router.post(
                      `/encounters/${encounter.id}/start`,
                      { chair_id: chairId || null },
                      { preserveScroll: true },
                    );
                  }}
                />
              </>
            ) : null}
            {can.close && !encounter.is_closed ? (
              <ConfirmAction
                label="Tutup kunjungan"
                message="Catatan terkunci. Perbaikan setelah ini hanya dalam 2x24 jam oleh dokter atau lewat addendum."
                confirmLabel="Ya, tutup"
                onConfirm={() => {
                  router.post(`/encounters/${encounter.id}/close`, {}, { preserveScroll: true });
                }}
              />
            ) : null}
          </div>
        </div>

        <MedicalWarnings warnings={warnings} />

        {encounter.is_closed ? (
          <p className="text-sm text-slate-600">
            Kunjungan ditutup{' '}
            {encounter.closed_at ? formatDateTime(encounter.closed_at, timezone) : ''}. Perubahan
            hanya lewat perbaikan 2x24 jam oleh dokter atau addendum.
          </p>
        ) : null}

        <div role="tablist" aria-label="Bagian pemeriksaan" className="tabs">
          {TABS.map((item) => (
            <button
              key={item.key}
              id={`tab-${item.key}`}
              role="tab"
              type="button"
              aria-selected={tab === item.key}
              aria-controls={`panel-${item.key}`}
              onClick={() => {
                setTab(item.key);
              }}
              className="tab"
            >
              {item.label}
            </button>
          ))}
        </div>

        <div id={`panel-${tab}`} role="tabpanel" aria-labelledby={`tab-${tab}`}>
          {tab === 'anamnesis' ? (
            <AnamnesisPanel
              key={encounter.id + String(encounter.vitals.pulse)}
              encounter={encounter}
              canWrite={can.write}
            />
          ) : null}
          {tab === 'soap' ? (
            <SoapPanel
              key={notes.map((n) => n.id).join()}
              encounter={encounter}
              notes={notes}
              canWrite={can.write}
            />
          ) : null}
          {tab === 'odontogram' ? (
            <OdontogramBoard
              patientId={encounter.patient_id}
              encounterId={encounter.id}
              canWrite={can.odontogram && !encounter.is_closed}
            />
          ) : null}
          {tab === 'diagnosis' ? (
            <DiagnosisPanel encounterId={encounter.id} entries={diagnoses} canEdit={canEdit} />
          ) : null}
          {tab === 'procedures' ? (
            <ProcedurePanel
              encounterId={encounter.id}
              entries={procedures}
              jaws={jaws}
              canEdit={canEdit}
            />
          ) : null}
          {tab === 'prescriptions' ? (
            <PrescriptionPanel
              encounterId={encounter.id}
              prescriptions={prescriptions}
              canCreate={can.prescribe && !encounter.is_closed}
              canVoid={can.prescribe && !encounter.is_closed}
              canPrint={can.printPrescription}
              canOverrideStock={can.overrideStock}
            />
          ) : null}
          {tab === 'files' ? (
            <FilesPanel encounterId={encounter.id} files={files} canEdit={canEdit} />
          ) : null}
          {tab === 'addenda' ? (
            <div className="flex flex-col gap-6">
              <AddendumPanel
                encounterId={encounter.id}
                addenda={addenda}
                canAdd={can.addendum && encounter.is_closed}
              />
              <section aria-label="Riwayat versi catatan" className="flex max-w-2xl flex-col gap-2">
                <h3 className="text-base font-semibold">Riwayat versi catatan</h3>
                {history.length === 0 ? (
                  <p className="text-sm text-slate-600">Belum ada catatan.</p>
                ) : null}
                <ul className="flex flex-col gap-1 text-sm">
                  {history.map((note) => (
                    <li key={note.id}>
                      {formatDateTime(note.authored_at, timezone)} - {note.author}: {note.type}
                      {note.section
                        ? ` (${note.section === 'extra_oral' ? 'ekstra oral' : 'intra oral'})`
                        : ''}
                    </li>
                  ))}
                </ul>
              </section>
            </div>
          ) : null}
        </div>
      </main>
    </AppLayout>
  );
}
