import { Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Badge } from '@/components/ui/Badge';
import { formatDateTime, formatRupiah } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Procedure {
  id: string;
  code: string;
  name: string;
  tooth_fdi: number | null;
  quantity: number;
  unit_price_amount: number;
  subtotal_amount: number;
}

interface Encounter {
  id: string;
  started_at: string;
  status: string;
  status_label: string;
  doctor_name: string | null;
  chief_complaint: string | null;
  assessment: string | null;
  plan: string | null;
  addendum_count: number;
  diagnoses: { id: string; code: string; name: string; tooth_fdi: number | null }[];
  procedures: Procedure[];
  files: { id: string; kind_label: string; tooth_fdi: number | null; caption: string | null }[];
}

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
  encounters: Encounter[];
}

export default function Timeline({ patient, encounters }: Props) {
  const timezone = useClinicTimezone();

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-4xl flex-col gap-4 p-6 lg:px-12 lg:py-10">
        <PageHead title="Riwayat kunjungan" />
        <div>
          <h1>Riwayat kunjungan</h1>
          <p className="text-muted text-sm">
            {patient.name} | Nomor RM {patient.medical_record_no} |{' '}
            <Link href={`/patients/${patient.id}`} className="text-ink underline">
              Data pasien
            </Link>
          </p>
        </div>

        {encounters.length === 0 ? (
          <p className="text-muted text-sm">Belum ada kunjungan.</p>
        ) : null}

        <ol className="flex flex-col gap-4">
          {encounters.map((encounter) => (
            <li key={encounter.id} className="card flex flex-col gap-2 p-4">
              <div className="flex flex-wrap items-center gap-2">
                <h2>{formatDateTime(encounter.started_at, timezone)}</h2>
                <Badge tone={encounter.status === 'open' ? 'warning' : 'neutral'}>
                  {encounter.status_label}
                </Badge>
                <span className="text-muted text-sm">{encounter.doctor_name ?? '-'}</span>
                <Link href={`/encounters/${encounter.id}`} className="text-ink text-sm underline">
                  Buka pemeriksaan
                </Link>
              </div>
              {encounter.chief_complaint ? (
                <p className="text-sm">Keluhan: {encounter.chief_complaint}</p>
              ) : null}
              {encounter.diagnoses.length > 0 ? (
                <p className="text-sm">
                  Diagnosa:{' '}
                  {encounter.diagnoses
                    .map(
                      (d) =>
                        `${d.code} ${d.name}${d.tooth_fdi ? ` (gigi ${String(d.tooth_fdi)})` : ''}`,
                    )
                    .join('; ')}
                </p>
              ) : null}
              {encounter.assessment ? (
                <p className="text-sm">Asesmen: {encounter.assessment}</p>
              ) : null}
              {encounter.plan ? <p className="text-sm">Rencana: {encounter.plan}</p> : null}
              {encounter.procedures.length > 0 ? (
                <ul className="list-disc pl-5 text-sm">
                  {encounter.procedures.map((p) => (
                    <li key={p.id}>
                      {p.code} {p.name}
                      {p.tooth_fdi ? ` gigi ${String(p.tooth_fdi)}` : ''} x{p.quantity} (
                      {formatRupiah(p.subtotal_amount)})
                    </li>
                  ))}
                </ul>
              ) : null}
              {encounter.files.length > 0 ? (
                <p className="text-sm">
                  Berkas:{' '}
                  {encounter.files.map((f, index) => (
                    <span key={f.id}>
                      {index > 0 ? ', ' : ''}
                      <a
                        href={`/clinical-files/${f.id}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-ink underline"
                      >
                        {f.caption ?? f.kind_label}
                      </a>
                    </span>
                  ))}
                </p>
              ) : null}
              {encounter.addendum_count > 0 ? (
                <p className="text-muted text-xs">{encounter.addendum_count} addendum</p>
              ) : null}
            </li>
          ))}
        </ol>
      </main>
    </AppLayout>
  );
}
