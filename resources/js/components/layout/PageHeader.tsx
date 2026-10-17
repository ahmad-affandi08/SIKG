import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface PageHeaderProps {
  title: string;
  /** Tautan kembali ke halaman daftar; dipakai pada halaman formulir. */
  back?: { href: string; label: string };
  description?: string;
  /** Tombol aksi utama halaman (misalnya Tambah), di sisi kanan judul. */
  actions?: ReactNode;
}

/** Kepala halaman seragam: bar alat dengan judul di kiri, tautan kembali dan aksi di kanan. */
export function PageHeader({ title, back, description, actions }: PageHeaderProps) {
  return (
    <header className="flex flex-col gap-1">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-[#b9b9b9] pb-2">
        <div className="flex min-w-0 flex-wrap items-baseline gap-x-3">
          <h1>{title}</h1>
          {description ? <p className="text-muted text-xs">{description}</p> : null}
        </div>
        <div className="flex flex-wrap items-center gap-1.5">
          {back ? (
            <Link href={back.href} className="btn btn-secondary">
              Kembali ke {back.label}
            </Link>
          ) : null}
          {actions}
        </div>
      </div>
    </header>
  );
}
