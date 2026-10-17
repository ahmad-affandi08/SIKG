import { router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Select } from '@/components/ui/Select';
import type { OdontogramData } from '@/features/clinical/odontogram/api';
import { OdontogramChart } from '@/features/clinical/odontogram/OdontogramChart';
import {
  changedTeeth,
  conditionName,
  hasPrimaryTeeth,
  surfaceName,
} from '@/features/clinical/odontogram/compare';
import type { OdontogramChange } from '@/features/clinical/odontogram/compare';
import { formatDateTime } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
  encounters: { id: string; started_at: string; status: string }[];
  left_id: string | null;
  right_id: string | null;
  left: OdontogramData | null;
  right: OdontogramData | null;
  changes: OdontogramChange[];
}

/** ODO-07: odontogram dua kunjungan berdampingan; gigi yang berbeda digaris putus-putus dan dirinci di tabel. */
export default function OdontogramCompare({
  patient,
  encounters,
  left_id: leftId,
  right_id: rightId,
  left,
  right,
  changes,
}: Props) {
  const timezone = useClinicTimezone();
  const options = encounters.map((e) => ({
    value: e.id,
    label: formatDateTime(e.started_at, timezone),
  }));
  const changed = changedTeeth(changes);
  const primary = hasPrimaryTeeth(left, right);

  function choose(next: { left?: string; right?: string }) {
    router.get(
      `/patients/${patient.id}/odontogram/compare`,
      { left: next.left ?? leftId ?? '', right: next.right ?? rightId ?? '' },
      { preserveState: true, preserveScroll: true },
    );
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Bandingkan odontogram" />
        <PageHeader
          title="Bandingkan odontogram"
          back={{ href: `/patients/${patient.id}`, label: 'rekam pasien' }}
          description={`${patient.name} (${patient.medical_record_no})`}
        />

        {left === null || right === null || leftId === null || rightId === null ? (
          <p className="text-muted text-sm">
            Pasien belum memiliki kunjungan, sehingga belum ada odontogram untuk dibandingkan.
          </p>
        ) : (
          <>
            <div className="flex flex-wrap items-end gap-3">
              <Select
                label="Kunjungan sebelah kiri"
                options={options}
                value={leftId}
                onChange={(event) => {
                  choose({ left: event.target.value });
                }}
              />
              <Select
                label="Kunjungan sebelah kanan"
                options={options}
                value={rightId}
                onChange={(event) => {
                  choose({ right: event.target.value });
                }}
              />
            </div>
            <p className="text-muted text-sm">
              Keadaan odontogram sampai akhir kunjungan yang dipilih. Gigi yang berbeda digaris
              putus-putus.
            </p>

            <div className="grid gap-4 lg:grid-cols-2">
              <section aria-label="Odontogram kiri" className="flex flex-col gap-2">
                <h2>Kiri</h2>
                <OdontogramChart
                  data={left}
                  changed={changed}
                  includePrimary={primary}
                  label="Odontogram kunjungan kiri"
                />
              </section>
              <section aria-label="Odontogram kanan" className="flex flex-col gap-2">
                <h2>Kanan</h2>
                <OdontogramChart
                  data={right}
                  changed={changed}
                  includePrimary={primary}
                  label="Odontogram kunjungan kanan"
                />
              </section>
            </div>

            <section aria-label="Perbedaan" className="flex flex-col gap-2">
              <h2>Perbedaan</h2>
              {changes.length === 0 ? (
                <p className="text-sm">Tidak ada perbedaan antara kedua kunjungan.</p>
              ) : (
                <table className="w-full text-sm">
                  <thead>
                    <tr>
                      <th scope="col">Gigi</th>
                      <th scope="col">Bagian</th>
                      <th scope="col">Kiri</th>
                      <th scope="col">Kanan</th>
                    </tr>
                  </thead>
                  <tbody>
                    {changes.map((change) => (
                      <tr key={`${String(change.fdi)}-${change.surface}`}>
                        <td>{change.fdi}</td>
                        <td>{surfaceName(change.surface)}</td>
                        <td>{conditionName(left, change.from)}</td>
                        <td>{conditionName(right, change.to)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </section>
          </>
        )}
      </main>
    </AppLayout>
  );
}
