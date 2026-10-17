import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import type { SelectOption } from '@/components/ui/Select';
import { formatDate, formatDateTime } from '@/lib/format';

interface History {
  id: string;
  type: string;
  name: string;
  severity: string | null;
  notes: string | null;
  is_warning: boolean;
}

interface Props {
  patient: {
    id: string;
    medical_record_no: string;
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
    is_minor: boolean;
    has_consent: boolean;
  };
  histories: History[];
  consents: { version: string; given_at: string; recorded_by: string }[];
  currentConsentVersion: string;
  can: { update: boolean; archive: boolean; merge: boolean; history: boolean; timeline: boolean };
  sexes?: SelectOption[];
  historyTypes: SelectOption[];
  severities: SelectOption[];
}

function Row({ label, value }: { label: string; value: string | null }) {
  return (
    <div className="flex flex-col sm:flex-row sm:gap-3">
      <dt className="text-muted w-48 shrink-0 text-sm">{label}</dt>
      <dd className="text-sm">{value ?? '-'}</dd>
    </div>
  );
}

export default function Show({
  patient,
  histories,
  consents,
  currentConsentVersion,
  can,
  historyTypes,
  severities,
}: Props) {
  const labelOf = (options: SelectOption[], value: string | null) =>
    options.find((o) => o.value === value)?.label ?? value;
  const warnings = histories.filter((h) => h.is_warning);
  const consentOutdated = consents[0]?.version !== currentConsentVersion;

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Rekam pasien" />
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1>{patient.name}</h1>
            <p className="text-muted text-sm">Nomor RM {patient.medical_record_no}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            {can.timeline ? (
              <Link href={`/patients/${patient.id}/timeline`} className="btn btn-secondary">
                Riwayat kunjungan
              </Link>
            ) : null}
            {can.update ? (
              <Link href={`/patients/${patient.id}/edit`} className="btn btn-secondary">
                Ubah data
              </Link>
            ) : null}
            {can.timeline ? (
              <Link href={`/patients/${patient.id}/treatment-plans`} className="btn btn-secondary">
                Rencana perawatan
              </Link>
            ) : null}
            {can.merge ? (
              <Link href={`/patients/${patient.id}/merge`} className="btn btn-secondary">
                Gabung data ganda
              </Link>
            ) : null}
            {can.archive ? (
              <ConfirmAction
                label="Arsipkan pasien"
                message="Pasien tidak muncul lagi di pencarian. Riwayat kunjungan dan tagihan tetap tersimpan."
                confirmLabel="Ya, arsipkan"
                onConfirm={() => {
                  router.delete(`/patients/${patient.id}`);
                }}
              />
            ) : null}
          </div>
        </div>

        {warnings.length > 0 ? (
          <Alert tone="warning">
            <span className="font-semibold">Peringatan medis:</span>{' '}
            {warnings
              .map((w) => `${w.name}${w.severity ? ` (${labelOf(severities, w.severity)})` : ''}`)
              .join('; ')}
          </Alert>
        ) : null}

        <section aria-label="Data pasien">
          <dl className="flex flex-col gap-2">
            <Row label="NIK" value={patient.nik} />
            <Row label="Alasan NIK belum ada" value={patient.nik_missing_reason} />
            <Row label="Tanggal lahir" value={formatDate(patient.birth_date)} />
            <Row label="Alamat" value={patient.address} />
            <Row label="Nomor HP" value={patient.phone} />
            <Row label="Pekerjaan" value={patient.occupation} />
            <Row
              label="Kontak darurat"
              value={
                patient.emergency_name
                  ? `${patient.emergency_name} (${patient.emergency_relation ?? '-'}) ${patient.emergency_phone ?? ''}`
                  : null
              }
            />
            <Row
              label="Wali"
              value={
                patient.guardian_name
                  ? `${patient.guardian_name} (${patient.guardian_relation ?? '-'}) ${patient.guardian_phone ?? ''}`
                  : null
              }
            />
          </dl>
        </section>

        <section aria-label="Riwayat medis" className="flex flex-col gap-3">
          <h2>Riwayat medis</h2>
          {histories.length === 0 ? (
            <p className="text-muted text-sm">Belum ada riwayat medis.</p>
          ) : (
            <ul className="flex flex-col gap-2">
              {histories.map((h) => (
                <li key={h.id} className="card flex flex-wrap items-center gap-2 p-2">
                  <Badge tone={h.is_warning ? 'danger' : 'neutral'}>
                    {labelOf(historyTypes, h.type)}
                  </Badge>
                  <span className="text-sm font-medium">{h.name}</span>
                  {h.severity ? (
                    <span className="text-sm">({labelOf(severities, h.severity)})</span>
                  ) : null}
                  {h.notes ? <span className="text-muted text-sm">{h.notes}</span> : null}
                  {can.history ? (
                    <ConfirmAction
                      label={`Hapus ${h.name}`}
                      message="Catatan dihapus dari daftar aktif; jejaknya tetap tersimpan di audit."
                      confirmLabel="Ya, hapus"
                      onConfirm={() => {
                        router.delete(`/patients/${patient.id}/history/${h.id}`, {
                          preserveScroll: true,
                        });
                      }}
                    />
                  ) : null}
                </li>
              ))}
            </ul>
          )}

          {can.history ? (
            <div>
              <Link href={`/patients/${patient.id}/history/create`} className="btn btn-secondary">
                Tambah riwayat medis
              </Link>
            </div>
          ) : null}
        </section>

        <section aria-label="Persetujuan data" className="flex flex-col gap-3">
          <h2>Persetujuan pemrosesan data</h2>
          {consents.length === 0 ? (
            <Alert tone="warning">Persetujuan data pasien belum dicatat.</Alert>
          ) : (
            <ul className="flex flex-col gap-1 text-sm">
              {consents.map((c, index) => (
                <li key={index}>
                  Teks versi {c.version}, {formatDateTime(c.given_at)}, dicatat oleh {c.recorded_by}
                </li>
              ))}
            </ul>
          )}
          {can.update && consentOutdated ? (
            <div>
              <ConfirmAction
                label="Catat persetujuan"
                message={`Pasien menyetujui teks persetujuan versi ${currentConsentVersion} hari ini.`}
                confirmLabel="Ya, catat"
                variant="primary"
                onConfirm={() => {
                  router.post(`/patients/${patient.id}/consent`, {}, { preserveScroll: true });
                }}
              />
            </div>
          ) : null}
        </section>
      </main>
    </AppLayout>
  );
}
