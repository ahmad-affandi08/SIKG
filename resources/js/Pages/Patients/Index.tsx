import { Link } from '@inertiajs/react';
import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { usePatientSearch } from '@/features/patient/queries';
import { formatDate } from '@/lib/format';
import { useCan } from '@/lib/permissions';
import { useDebouncedValue } from '@/lib/useDebouncedValue';

interface Props {
  sexes: { value: string; label: string }[];
}

export default function Index({ sexes }: Props) {
  const can = useCan();
  const [text, setText] = useState('');
  const term = useDebouncedValue(text.trim(), 300);
  const query = usePatientSearch(term);
  const sexLabel = (value: string) => sexes.find((s) => s.value === value)?.label ?? value;

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Pasien" />
        <PageHeader
          title="Pasien"
          actions={
            <>
              {can('patient.import') ? (
                <Link href="/patients/import" className="btn btn-secondary">
                  Impor dari Excel
                </Link>
              ) : null}
              {can('patient.create') ? (
                <Link href="/patients/create" className="btn btn-primary">
                  Daftarkan pasien baru
                </Link>
              ) : null}
            </>
          }
        />

        <div className="flex flex-col gap-1">
          <label htmlFor="patient-search" className="text-sm font-semibold">
            Cari pasien (nama, nomor RM, NIK, atau nomor HP)
          </label>
          <input
            id="patient-search"
            type="search"
            autoComplete="off"
            value={text}
            onChange={(event) => {
              setText(event.target.value);
            }}
            className="field"
          />
        </div>

        {query.isError ? (
          <p role="alert" className="text-danger-600 text-sm">
            Pencarian gagal. Coba lagi.
          </p>
        ) : null}

        {term === '' ? (
          <p className="text-muted text-sm">Ketik untuk mencari pasien.</p>
        ) : query.isSuccess && query.data.length === 0 ? (
          <p className="text-muted text-sm">Pasien tidak ditemukan.</p>
        ) : null}

        {query.data && query.data.length > 0 ? (
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Hasil pencarian pasien</caption>
            <thead>
              <tr>
                <th scope="col" className="py-2">
                  Nomor RM
                </th>
                <th scope="col">Nama</th>
                <th scope="col">Tanggal lahir</th>
                <th scope="col">Jenis kelamin</th>
                <th scope="col">HP</th>
              </tr>
            </thead>
            <tbody>
              {query.data.map((patient) => (
                <tr key={patient.id}>
                  <td className="py-2 pr-3">{patient.medical_record_no}</td>
                  <td className="pr-3">
                    <Link href={`/patients/${patient.id}`} className="text-ink underline">
                      {patient.name}
                    </Link>
                  </td>
                  <td className="pr-3">{formatDate(patient.birth_date)}</td>
                  <td className="pr-3">{sexLabel(patient.sex)}</td>
                  <td>{patient.phone ?? '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : null}
      </main>
    </AppLayout>
  );
}
