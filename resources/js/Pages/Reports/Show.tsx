import { router } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Button } from '@/components/ui/Button';
import { DateRangePicker } from '@/components/ui/DateRangePicker';
import type { DateRange } from '@/components/ui/DateRangePicker';
import { Select } from '@/components/ui/Select';
import { ExportPanel } from '@/features/reporting/components/ExportPanel';
import { ReportView } from '@/features/reporting/components/ReportView';
import type { Report, ReportFilterValues } from '@/features/reporting/schemas';
import { formatDate } from '@/lib/format';

interface Option {
  id: string;
  name: string;
}

interface Props {
  report: Report;
  filters: ReportFilterValues;
  options: { doctors: Option[]; methods: Option[]; grain: boolean };
  scope: 'full' | 'own';
  description: string;
}

const GRAINS = [
  { value: 'day', label: 'Harian' },
  { value: 'month', label: 'Bulanan' },
];

/** Satu laporan: penyaring (rentang tanggal, dokter, metode bayar), isi laporan, dan ekspor PDF atau Excel. */
export default function Show({ report, filters, options, scope, description }: Props) {
  const [range, setRange] = useState<DateRange>({ from: filters.from, to: filters.to });
  const [doctor, setDoctor] = useState(filters.doctor_id);
  const [method, setMethod] = useState(filters.method_id);
  const [grain, setGrain] = useState(filters.grain);

  function apply() {
    const query: Record<string, string> = {};
    if (range.from) query.from = range.from;
    if (range.to) query.to = range.to;
    if (doctor) query.doctor_id = doctor;
    if (method) query.method_id = method;
    if (options.grain && grain) query.grain = grain;
    router.get(`/reports/${report.key}`, query, { preserveScroll: true, preserveState: true });
  }

  const period =
    report.from === report.to
      ? formatDate(report.from)
      : `${formatDate(report.from)} - ${formatDate(report.to)}`;

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title={report.title} />
        <PageHeader
          title={report.title}
          description={`Periode ${period}`}
          back={{ href: '/reports', label: 'Laporan' }}
        />
        <p className="text-muted text-sm">{description}</p>
        {scope === 'own' ? (
          <p className="text-muted text-xs">Laporan ini dibatasi pada data milik Anda.</p>
        ) : null}

        <form
          className="flex flex-wrap items-end gap-3"
          aria-label="Penyaring laporan"
          onSubmit={(event) => {
            event.preventDefault();
            apply();
          }}
        >
          <DateRangePicker label="Rentang tanggal" value={range} onChange={setRange} />
          {options.doctors.length > 0 ? (
            <Select
              label="Dokter"
              placeholder="Semua dokter"
              options={options.doctors.map((d) => ({ value: d.id, label: d.name }))}
              value={doctor}
              onChange={(e) => {
                setDoctor(e.target.value);
              }}
            />
          ) : null}
          {options.methods.length > 0 ? (
            <Select
              label="Metode bayar"
              placeholder="Semua metode"
              options={options.methods.map((m) => ({ value: m.id, label: m.name }))}
              value={method}
              onChange={(e) => {
                setMethod(e.target.value);
              }}
            />
          ) : null}
          {options.grain ? (
            <Select
              label="Penyajian"
              options={GRAINS}
              value={grain}
              onChange={(e) => {
                setGrain(e.target.value);
              }}
            />
          ) : null}
          <Button type="submit" variant="secondary">
            Terapkan
          </Button>
        </form>

        <ExportPanel
          reportKey={report.key}
          filters={{
            from: filters.from,
            to: filters.to,
            doctor_id: filters.doctor_id,
            method_id: filters.method_id,
            grain: filters.grain,
          }}
        />

        {report.key === 'doctor-fees' && filters.doctor_id !== '' ? (
          <p>
            <a
              className="btn btn-secondary"
              href={`/reports/doctor-fees/slip?${new URLSearchParams({ doctor_id: filters.doctor_id, from: filters.from, to: filters.to }).toString()}`}
              target="_blank"
              rel="noopener noreferrer"
            >
              Cetak slip jasa dokter
            </a>
          </p>
        ) : null}

        <ReportView report={report} />
      </main>
    </AppLayout>
  );
}
