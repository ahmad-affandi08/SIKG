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

/** Kepala halaman seragam: tautan kembali, judul, keterangan, dan aksi. */
export function PageHeader({ title, back, description, actions }: PageHeaderProps) {
  return (
    <header className="flex flex-col gap-3">
      {back ? (
        <Link href={back.href} className="text-muted hover:text-signal-700 w-fit text-sm underline">
          Kembali ke {back.label}
        </Link>
      ) : null}
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1>{title}</h1>
          {description ? <p className="text-muted mt-1 text-sm">{description}</p> : null}
        </div>
        {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
      </div>
    </header>
  );
}
