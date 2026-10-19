import type { ReactNode } from 'react';

/** Ilustrasi yang tersedia. Pilih yang paling dekat dengan isi daftar yang kosong. */
export type EmptyKind =
  | 'tooth'
  | 'calendar'
  | 'queue'
  | 'invoice'
  | 'pill'
  | 'chart'
  | 'bell'
  | 'folder'
  | 'search'
  | 'box';

type EmptySize = 'page' | 'section' | 'compact';

interface EmptyStateProps {
  kind?: EmptyKind;
  title: string;
  /** Satu atau dua kalimat: mengapa kosong dan apa yang dapat dilakukan. */
  description?: ReactNode;
  /** Tombol atau tautan tindakan berikutnya (opsional). */
  action?: ReactNode;
  /** `page` untuk halaman penuh, `section` untuk panel, `compact` untuk tab, sel tabel, atau kartu kecil. */
  size?: EmptySize;
}

const SIZES: Record<EmptySize, { box: string; art: string; title: string }> = {
  // Halaman penuh: setinggi sisa ruang di bawah judul halaman (tinggi layar dikurangi menubar, jejak, judul, dan bilah status).
  page: { box: 'min-h-[max(26rem,calc(100dvh-17rem))] py-8', art: 'w-60', title: 'text-xl' },
  section: { box: 'min-h-[15rem] py-6', art: 'w-44', title: 'text-lg' },
  compact: { box: 'min-h-[9.5rem] py-4', art: 'w-28', title: 'text-base' },
};

/** Latar bersama: lingkaran lembut, bayangan lantai, dan beberapa kilau kecil di sekitar objek. */
function Backdrop() {
  return (
    <>
      <circle cx="100" cy="70" r="58" className="fill-brand-50" />
      <circle cx="100" cy="70" r="58" className="stroke-brand-100 fill-none" strokeWidth="1.5" />
      <ellipse cx="100" cy="138" rx="58" ry="7" className="fill-chrome" />
      <g className="fill-brand-200">
        <circle cx="30" cy="48" r="3" />
        <circle cx="172" cy="104" r="3.5" />
        <circle cx="164" cy="34" r="2" />
        <circle cx="38" cy="108" r="2" />
      </g>
      <g className="stroke-warning-500" strokeWidth="2.2" strokeLinecap="round">
        <path d="M168 52v10M163 57h10" />
        <path d="M30 78v7M26.5 81.5h7" />
      </g>
    </>
  );
}

function Tooth() {
  return (
    <>
      <path
        d="M72 40c0-12 12-17 22-13 4 1.6 8 1.6 12 0 10-4 22 1 22 13 0 14-6 21-8 37-2 16-4 33-11 33s-7-17-13-17-6 17-13 17-9-17-11-33c-2-16-8-23-8-37Z"
        className="stroke-brand-600 fill-white"
        strokeWidth="3"
        strokeLinejoin="round"
      />
      <path
        d="M80 38c0-6 4-9 9-8"
        className="stroke-brand-200 fill-none"
        strokeWidth="3"
        strokeLinecap="round"
      />
      <g className="fill-brand-700">
        <circle cx="90" cy="54" r="2.8" />
        <circle cx="110" cy="54" r="2.8" />
      </g>
      <path
        d="M94 63c3 3.4 9 3.4 12 0"
        className="stroke-brand-700 fill-none"
        strokeWidth="2.6"
        strokeLinecap="round"
      />
      <g className="fill-signal/15">
        <circle cx="82" cy="62" r="4.5" />
        <circle cx="118" cy="62" r="4.5" />
      </g>
    </>
  );
}

function Calendar() {
  return (
    <>
      <rect
        x="58"
        y="34"
        width="84"
        height="78"
        rx="6"
        className="stroke-brand-600 fill-white"
        strokeWidth="3"
      />
      <path d="M58 40a6 6 0 0 1 6-6h72a6 6 0 0 1 6 6v14H58Z" className="fill-brand-600" />
      <g className="stroke-brand-700" strokeWidth="4" strokeLinecap="round">
        <path d="M78 27v13M122 27v13" />
      </g>
      <g className="fill-brand-100">
        <rect x="68" y="64" width="14" height="11" rx="2" />
        <rect x="86" y="64" width="14" height="11" rx="2" />
        <rect x="104" y="64" width="14" height="11" rx="2" />
        <rect x="68" y="80" width="14" height="11" rx="2" />
        <rect x="104" y="80" width="14" height="11" rx="2" />
        <rect x="68" y="96" width="14" height="9" rx="2" />
        <rect x="86" y="96" width="14" height="9" rx="2" />
      </g>
      <rect
        x="86"
        y="80"
        width="14"
        height="11"
        rx="2"
        className="stroke-warning-500 fill-none"
        strokeWidth="2"
        strokeDasharray="3 2.5"
      />
      <circle cx="130" cy="100" r="17" className="stroke-brand-600 fill-white" strokeWidth="3" />
      <path
        d="M130 90v10l7 4"
        className="stroke-brand-700 fill-none"
        strokeWidth="3"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </>
  );
}

function Person({ x, solid }: { x: number; solid?: boolean }) {
  const stroke = solid ? 'stroke-brand-600' : 'stroke-brand-500';
  const fill = solid ? 'fill-brand-100' : 'fill-white';
  const dash = solid ? undefined : '4 3';
  return (
    <g transform={`translate(${String(x)} 0)`}>
      <circle
        cx="0"
        cy="70"
        r="10"
        className={`${fill} ${stroke}`}
        strokeWidth="2.8"
        strokeDasharray={dash}
      />
      <path
        d="M-17 116c0-14 8-24 17-24s17 10 17 24Z"
        className={`${fill} ${stroke}`}
        strokeWidth="2.8"
        strokeLinejoin="round"
        strokeDasharray={dash}
      />
    </g>
  );
}

function Queue() {
  return (
    <>
      <Person x={60} />
      <Person x={100} solid />
      <Person x={140} />
      <g>
        <rect
          x="76"
          y="24"
          width="48"
          height="22"
          rx="4"
          className="stroke-brand-600 fill-white"
          strokeWidth="2.8"
        />
        <circle
          cx="76"
          cy="35"
          r="3.4"
          className="fill-brand-50 stroke-brand-600"
          strokeWidth="2"
        />
        <circle
          cx="124"
          cy="35"
          r="3.4"
          className="fill-brand-50 stroke-brand-600"
          strokeWidth="2"
        />
        <path d="M92 35h16" className="stroke-brand-700" strokeWidth="3.2" strokeLinecap="round" />
      </g>
    </>
  );
}

function Invoice() {
  return (
    <>
      <path
        d="M64 28h72v90l-9-6-9 6-9-6-9 6-9-6-9 6-9-6Z"
        className="stroke-brand-600 fill-white"
        strokeWidth="3"
        strokeLinejoin="round"
      />
      <g className="stroke-brand-200" strokeWidth="4" strokeLinecap="round">
        <path d="M76 46h48M76 60h48M76 74h30" />
      </g>
      <path d="M76 90h22" className="stroke-brand-600" strokeWidth="4" strokeLinecap="round" />
      <circle cx="136" cy="94" r="19" className="stroke-warning-500 fill-white" strokeWidth="3" />
      <circle
        cx="136"
        cy="94"
        r="13"
        className="stroke-warning-500 fill-none"
        strokeWidth="1.6"
        strokeDasharray="2.5 2.5"
      />
      <path
        d="M131 99.5c1.4 1.4 3.2 2 5.3 2 2.6 0 4.2-1.1 4.2-2.8 0-4.2-9.6-1.8-9.6-6.2 0-1.7 1.6-3 4.2-3 1.8 0 3.4.5 4.6 1.5M136 86v5M136 101v5"
        className="stroke-warning-500 fill-none"
        strokeWidth="2.2"
        strokeLinecap="round"
      />
    </>
  );
}

function Pill() {
  return (
    <>
      <rect
        x="64"
        y="50"
        width="44"
        height="66"
        rx="8"
        className="stroke-brand-600 fill-white"
        strokeWidth="3"
      />
      <rect
        x="70"
        y="36"
        width="32"
        height="18"
        rx="4"
        className="fill-brand-600 stroke-brand-700"
        strokeWidth="2.4"
      />
      <rect x="64" y="70" width="44" height="26" className="fill-brand-50" />
      <path
        d="M86 74v18M77 83h18"
        className="stroke-brand-600"
        strokeWidth="4.6"
        strokeLinecap="round"
      />
      <g transform="rotate(-35 128 100)">
        <rect
          x="108"
          y="90"
          width="40"
          height="20"
          rx="10"
          className="stroke-brand-600 fill-white"
          strokeWidth="3"
        />
        <path d="M128 90h10a10 10 0 0 1 0 20h-10Z" className="fill-brand-200" />
        <path d="M128 90v20" className="stroke-brand-600" strokeWidth="3" />
      </g>
    </>
  );
}

function Chart() {
  return (
    <>
      <rect
        x="54"
        y="36"
        width="92"
        height="78"
        rx="5"
        className="stroke-brand-600 fill-white"
        strokeWidth="3"
      />
      <g className="fill-brand-100 stroke-brand-500" strokeWidth="2" strokeDasharray="4 3">
        <rect x="66" y="82" width="14" height="22" rx="2" />
        <rect x="88" y="66" width="14" height="38" rx="2" />
        <rect x="110" y="74" width="14" height="30" rx="2" />
      </g>
      <path d="M62 104h76" className="stroke-brand-600" strokeWidth="3" strokeLinecap="round" />
      <path
        d="M66 66l22-14 22 8 22-16"
        className="stroke-warning-500 fill-none"
        strokeWidth="3"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeDasharray="1 6"
      />
      <circle cx="132" cy="44" r="4" className="fill-warning-500" />
    </>
  );
}

function Bell() {
  return (
    <>
      <path
        d="M100 30c-16 0-27 12-27 28 0 18-6 24-11 30h76c-5-6-11-12-11-30 0-16-11-28-27-28Z"
        className="stroke-brand-600 fill-white"
        strokeWidth="3"
        strokeLinejoin="round"
      />
      <path
        d="M80 52c2-8 8-12 14-13"
        className="stroke-brand-200 fill-none"
        strokeWidth="3"
        strokeLinecap="round"
      />
      <path d="M100 30V24" className="stroke-brand-600" strokeWidth="3.6" strokeLinecap="round" />
      <path
        d="M90 94a10 10 0 0 0 20 0Z"
        className="fill-brand-600 stroke-brand-700"
        strokeWidth="2.2"
        strokeLinejoin="round"
      />
      <path
        d="M122 40c5 1 8 3.5 10 7M130 32c8 2 12 6 15 12"
        className="stroke-brand-200 fill-none"
        strokeWidth="3"
        strokeLinecap="round"
      />
      <g
        className="stroke-brand-500"
        strokeWidth="2.6"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <path d="M128 100h10l-10 10h10" className="fill-none" />
      </g>
    </>
  );
}

function Folder() {
  return (
    <>
      <path
        d="M52 46a6 6 0 0 1 6-6h28l9 10h47a6 6 0 0 1 6 6v54a6 6 0 0 1-6 6H58a6 6 0 0 1-6-6Z"
        className="fill-brand-200 stroke-brand-600"
        strokeWidth="3"
        strokeLinejoin="round"
      />
      <rect
        x="64"
        y="58"
        width="72"
        height="42"
        rx="3"
        className="stroke-brand-500 fill-white"
        strokeWidth="2.4"
        strokeDasharray="5 3.5"
      />
      <g className="stroke-brand-200" strokeWidth="4" strokeLinecap="round">
        <path d="M74 72h40M74 84h24" />
      </g>
      <path
        d="M52 70h96v44a6 6 0 0 1-6 6H58a6 6 0 0 1-6-6Z"
        className="fill-brand-100 stroke-brand-600"
        strokeWidth="3"
        strokeLinejoin="round"
      />
    </>
  );
}

function Search() {
  return (
    <>
      <rect
        x="56"
        y="30"
        width="66"
        height="84"
        rx="6"
        className="stroke-brand-600 fill-white"
        strokeWidth="3"
      />
      <g className="stroke-brand-200" strokeWidth="4" strokeLinecap="round">
        <path d="M68 48h30M68 62h22M68 76h16" />
      </g>
      <circle cx="116" cy="88" r="24" className="stroke-brand-700 fill-white/80" strokeWidth="4" />
      <circle cx="116" cy="88" r="17" className="fill-brand-50" />
      <path
        d="M107 79l18 18M125 79l-18 18"
        className="stroke-signal"
        strokeWidth="3.4"
        strokeLinecap="round"
      />
      <path d="M133 106l17 17" className="stroke-brand-700" strokeWidth="8" strokeLinecap="round" />
    </>
  );
}

function Box() {
  return (
    <>
      <path
        d="M58 62 100 46l42 16v50l-42 16-42-16Z"
        className="fill-brand-100 stroke-brand-600"
        strokeWidth="3"
        strokeLinejoin="round"
      />
      <path
        d="M58 62l42 16 42-16M100 78v50"
        className="stroke-brand-600 fill-none"
        strokeWidth="3"
        strokeLinejoin="round"
      />
      <path
        d="M58 62 40 48l42-14 18 12ZM142 62l18-14-42-14-18 12Z"
        className="stroke-brand-600 fill-white"
        strokeWidth="3"
        strokeLinejoin="round"
      />
      <path
        d="M86 60l14 5 14-5"
        className="stroke-brand-200 fill-none"
        strokeWidth="3"
        strokeLinecap="round"
      />
    </>
  );
}

const ART: Record<EmptyKind, () => ReactNode> = {
  tooth: Tooth,
  calendar: Calendar,
  queue: Queue,
  invoice: Invoice,
  pill: Pill,
  chart: Chart,
  bell: Bell,
  folder: Folder,
  search: Search,
  box: Box,
};

/**
 * Keadaan kosong: ilustrasi, judul, penjelasan, dan tindakan (opsional) yang selalu berada di tengah area, rata kiri-kanan
 * dan atas-bawah. Ilustrasi dekoratif (`aria-hidden`); teks yang dibaca pembaca layar. Warna mengikuti token tema, tanpa gambar
 * eksternal.
 */
export function EmptyState({
  kind = 'box',
  title,
  description,
  action,
  size = 'section',
}: EmptyStateProps) {
  const Art = ART[kind];
  const s = SIZES[size];
  return (
    <div
      role="status"
      data-empty-kind={kind}
      className={`mx-auto flex w-full max-w-xl flex-col items-center justify-center gap-1 px-4 text-center ${s.box}`}
    >
      <svg
        viewBox="0 0 200 150"
        aria-hidden="true"
        focusable="false"
        className={`mb-2 h-auto ${s.art}`}
        fill="none"
      >
        <Backdrop />
        <Art />
      </svg>
      <p className={`text-ink font-bold ${s.title}`}>{title}</p>
      {description ? <p className="text-muted max-w-md text-sm">{description}</p> : null}
      {action ? (
        <div className="mt-2 flex flex-wrap items-center justify-center gap-2">{action}</div>
      ) : null}
    </div>
  );
}
