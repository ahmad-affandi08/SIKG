import { Link } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { EmptyState } from '@/components/ui/EmptyState';
import { toDisplayDate } from '@/features/clinical/documents';

interface DocumentRow {
  id: string;
  type: string;
  type_label: string;
  number: string;
  title: string;
  doctor_name: string;
  encounter_id: string | null;
  issued_at: string;
  signable: boolean;
  signed: boolean;
  signed_at: string | null;
  signer_name: string | null;
}

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
  documents: DocumentRow[];
  can: { consent: boolean; letter: boolean };
  types: { value: string; label: string }[];
}

/** RM-09 dan RM-10: dokumen klinis yang sudah diterbitkan untuk pasien dan tombol menerbitkan yang baru. */
export default function Index({ patient, documents, can, types }: Props) {
  const allowed = types.filter((t) => (t.value === 'consent' ? can.consent : can.letter));

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Dokumen klinis" />
        <PageHeader
          title="Dokumen klinis"
          back={{ href: `/patients/${patient.id}`, label: 'rekam pasien' }}
          description={`${patient.name} (${patient.medical_record_no})`}
          actions={allowed.map((t) => (
            <Link
              key={t.value}
              href={`/patients/${patient.id}/documents/create?type=${t.value}`}
              className="btn btn-secondary"
            >
              {t.label}
            </Link>
          ))}
        />

        {documents.length === 0 ? (
          <EmptyState
            kind="folder"
            size="page"
            title="Belum ada dokumen yang diterbitkan"
            description="Surat keterangan, rujukan, dan persetujuan tindakan yang diterbitkan tersimpan di sini."
          />
        ) : (
          <table>
            <caption className="sr-only">Dokumen klinis pasien</caption>
            <thead>
              <tr>
                <th scope="col">Nomor</th>
                <th scope="col">Jenis</th>
                <th scope="col">Dokter</th>
                <th scope="col">Diterbitkan</th>
                <th scope="col">
                  <span className="sr-only">Aksi</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {documents.map((d) => (
                <tr key={d.id}>
                  <td>{d.number}</td>
                  <td>{d.type_label}</td>
                  <td>{d.doctor_name}</td>
                  <td>{`${toDisplayDate(d.issued_at)} ${d.issued_at.slice(11, 16)}`}</td>
                  <td>
                    <div className="flex flex-wrap items-center gap-1.5">
                      <a
                        href={`/documents/${d.id}/print`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="btn btn-secondary"
                      >
                        Cetak
                      </a>
                      {d.signed ? (
                        <>
                          <a
                            href={`/documents/${d.id}/signed`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="btn btn-secondary"
                          >
                            PDF bertanda tangan
                          </a>
                          <span className="text-muted text-xs">
                            Ditandatangani {d.signer_name}{' '}
                            {d.signed_at ? toDisplayDate(d.signed_at) : ''}
                          </span>
                        </>
                      ) : d.signable && can.consent ? (
                        <Link href={`/documents/${d.id}/sign`} className="btn btn-primary">
                          Tanda tangan di layar
                        </Link>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
