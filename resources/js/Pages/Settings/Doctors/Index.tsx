import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Badge } from '@/components/ui/Badge';
import { ConfirmAction } from '@/components/ui/ConfirmAction';

interface DoctorRow {
  id: string;
  name: string;
  sip_no: string | null;
  specialty: string | null;
  branch: string | null;
  user: string | null;
  is_active: boolean;
  schedule_count: number;
}

interface Props {
  doctors: DoctorRow[];
}

export default function Index({ doctors }: Props) {
  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Dokter" />
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h1>Dokter</h1>
          <Link href="/settings/doctors/create" className="btn btn-primary">
            Tambah dokter
          </Link>
        </div>

        <table className="w-full text-left text-sm">
          <caption className="sr-only">Daftar dokter klinik</caption>
          <thead>
            <tr>
              <th scope="col" className="py-2">
                Nama
              </th>
              <th scope="col">Nomor SIP</th>
              <th scope="col">Spesialisasi</th>
              <th scope="col">Cabang</th>
              <th scope="col">Akun</th>
              <th scope="col">Blok jadwal</th>
              <th scope="col">Status</th>
              <th scope="col">
                <span className="sr-only">Aksi</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {doctors.map((doctor) => (
              <tr key={doctor.id} className="align-top">
                <td className="py-2 pr-3 font-medium">{doctor.name}</td>
                <td className="pr-3">{doctor.sip_no ?? '-'}</td>
                <td className="pr-3">{doctor.specialty ?? '-'}</td>
                <td className="pr-3">{doctor.branch ?? 'Semua cabang'}</td>
                <td className="pr-3">{doctor.user ?? '-'}</td>
                <td className="pr-3">{doctor.schedule_count}</td>
                <td className="pr-3">
                  <Badge tone={doctor.is_active ? 'success' : 'neutral'}>
                    {doctor.is_active ? 'Aktif' : 'Nonaktif'}
                  </Badge>
                </td>
                <td>
                  <div className="flex flex-wrap gap-2">
                    <Link
                      href={`/settings/doctors/${doctor.id}/edit`}
                      className="btn btn-secondary"
                    >
                      Ubah
                    </Link>
                    {doctor.is_active ? (
                      <ConfirmAction
                        label={`Nonaktifkan ${doctor.name}`}
                        message={`${doctor.name} tidak muncul lagi di pilihan janji baru. Riwayatnya tetap tersimpan.`}
                        confirmLabel="Ya, nonaktifkan"
                        onConfirm={() => {
                          router.post(
                            `/settings/doctors/${doctor.id}/deactivate`,
                            {},
                            { preserveScroll: true },
                          );
                        }}
                      />
                    ) : (
                      <ConfirmAction
                        label={`Aktifkan ${doctor.name}`}
                        message={`${doctor.name} muncul kembali di pilihan janji.`}
                        confirmLabel="Ya, aktifkan"
                        variant="primary"
                        onConfirm={() => {
                          router.post(
                            `/settings/doctors/${doctor.id}/activate`,
                            {},
                            { preserveScroll: true },
                          );
                        }}
                      />
                    )}
                  </div>
                </td>
              </tr>
            ))}
            {doctors.length === 0 ? (
              <tr>
                <td colSpan={8} className="text-muted py-4">
                  Belum ada dokter.
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </main>
    </AppLayout>
  );
}
