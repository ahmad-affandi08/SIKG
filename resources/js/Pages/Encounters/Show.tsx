import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
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
import { useQueue } from '@/features/scheduling/queries';
import { useCan } from '@/lib/permissions';
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
  initialTab: TabKey;
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
    medical_history: medicalHistory,
    chairs,
    jaws,
    can,
    initialTab,
  } = props;
  const [tab, setTab] = useState<TabKey>(initialTab);
  const [chairId, setChairId] = useState(encounter.chair_id ?? '');
  const timezone = useClinicTimezone();
  const canEdit = can.write && !encounter.is_closed;

  return (
    <AppLayout bare>
      <PageHead title="Pemeriksaan" />
      <div className="min-h-0 flex-1 overflow-auto lg:overflow-visible">
        <div className="grid gap-1.5 lg:h-full lg:grid-cols-[14rem_minmax(0,1fr)_17rem]">
          <QueuePane currentPatientId={encounter.patient_id} />

          <main className="pane flex min-h-0 flex-col">
            <div className="pane-head flex-wrap">
              <h1 className="truncate">{encounter.patient_name ?? 'Pasien'}</h1>
              <div className="flex flex-wrap items-center gap-1.5 font-normal">
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
                      router.post(
                        `/encounters/${encounter.id}/close`,
                        {},
                        { preserveScroll: true },
                      );
                    }}
                  />
                ) : null}
              </div>
            </div>

            {encounter.is_closed ? (
              <p className="text-muted border-b border-[#d5d5d5] px-3 py-1.5">
                Kunjungan ditutup{' '}
                {encounter.closed_at ? formatDateTime(encounter.closed_at, timezone) : ''}.
                Perubahan hanya lewat perbaikan 2x24 jam oleh dokter atau addendum.
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

            <div
              id={`panel-${tab}`}
              role="tabpanel"
              aria-labelledby={`tab-${tab}`}
              className="min-h-0 flex-1 overflow-auto p-3"
            >
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
                  <section
                    aria-label="Riwayat versi catatan"
                    className="flex max-w-2xl flex-col gap-2"
                  >
                    <h3 className="text-base font-semibold">Riwayat versi catatan</h3>
                    {history.length === 0 ? (
                      <p className="text-muted text-sm">Belum ada catatan.</p>
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

          <aside aria-label="Ringkasan pasien" className="pane flex min-h-0 flex-col">
            <div className="pane-head">Ringkasan pasien</div>
            <div className="flex min-h-0 flex-1 flex-col gap-2 overflow-auto p-2">
              <dl className="grid grid-cols-[auto_1fr] gap-x-2 gap-y-1">
                <dt className="text-muted">Nomor RM</dt>
                <dd>{encounter.medical_record_no ?? '-'}</dd>
                <dt className="text-muted">Dokter</dt>
                <dd>{encounter.doctor_name ?? '-'}</dd>
                <dt className="text-muted">Kursi</dt>
                <dd>{encounter.chair_name ?? '-'}</dd>
                <dt className="text-muted">Mulai</dt>
                <dd>{formatDateTime(encounter.started_at, timezone)}</dd>
              </dl>
              <MedicalWarnings warnings={warnings} />
              {medicalHistory.length > 0 ? (
                <section aria-label="Riwayat medis">
                  <h3>Riwayat medis</h3>
                  <ul className="mt-1 list-disc pl-4">
                    {medicalHistory.map((item) => (
                      <li key={`${item.type}-${item.name}`}>
                        {item.type_label}: {item.name}
                      </li>
                    ))}
                  </ul>
                </section>
              ) : null}
              <p className="flex flex-wrap gap-1.5">
                <Link href={`/patients/${encounter.patient_id}`} className="btn btn-secondary">
                  Data pasien
                </Link>
                <Link
                  href={`/patients/${encounter.patient_id}/timeline`}
                  className="btn btn-secondary"
                >
                  Linimasa
                </Link>
              </p>
            </div>
          </aside>
        </div>
      </div>
    </AppLayout>
  );
}

/** Panel kiri: antrean hari ini (diperbarui otomatis); memilih pasien membuka pemeriksaannya. */
function QueuePane({ currentPatientId }: { currentPatientId: string }) {
  const queue = useQueue();
  const allowed = useCan()('encounter.create');
  const rows = queue.data ?? [];

  return (
    <aside aria-label="Antrean hari ini" className="pane flex min-h-0 flex-col">
      <div className="pane-head">
        <span>Antrean hari ini</span>
        <span className="text-muted font-normal">{rows.length}</span>
      </div>
      <ul className="min-h-0 flex-1 overflow-auto" aria-live="polite">
        {rows.map((item) => {
          const current = item.patient_id === currentPatientId;
          const openable =
            allowed && ['checked_in', 'in_progress', 'completed'].includes(item.status);
          const content = (
            <>
              <span className="w-5 shrink-0 text-right font-bold">{item.queue_no ?? '-'}</span>
              <span className="min-w-0 flex-1">
                <span className="block truncate">{item.patient_name ?? '-'}</span>
                <span className="text-muted block truncate">{item.status_label}</span>
              </span>
            </>
          );
          return (
            <li key={item.id} className="border-b border-[#d5d5d5]">
              {openable && !current ? (
                <button
                  type="button"
                  className="flex w-full items-start gap-2 px-2 py-1 text-left hover:bg-[#e4eefa]"
                  onClick={() => {
                    router.post('/encounters', { appointment_id: item.id });
                  }}
                >
                  {content}
                </button>
              ) : (
                <div
                  aria-current={current ? 'true' : undefined}
                  className={`flex items-start gap-2 px-2 py-1 ${current ? 'bg-brand-100 font-semibold' : ''}`}
                >
                  {content}
                </div>
              )}
            </li>
          );
        })}
        {queue.isSuccess && rows.length === 0 ? (
          <li className="text-muted p-2">Belum ada pasien dalam antrean.</li>
        ) : null}
      </ul>
    </aside>
  );
}
