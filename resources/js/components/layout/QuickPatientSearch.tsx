import { router } from '@inertiajs/react';
import type { KeyboardEvent } from 'react';
import { useCallback, useEffect, useId, useRef, useState } from 'react';

import { useDismiss } from '@/components/ui/useDismiss';
import { quickSearchPatients } from '@/components/layout/patientSearchApi';
import type { QuickPatient } from '@/components/layout/patientSearchApi';
import { formatDate } from '@/lib/format';
import { useDebouncedValue } from '@/lib/useDebouncedValue';

const MIN_NAME_LENGTH = 2;
const NIK = /^\d{16}$/;
const MEDICAL_RECORD_NO = /^[A-Za-z]{2,6}-\d+$/;

/** Hasil satu pencarian; `term` adalah kata kunci yang dipakai agar hasil lama tidak tampil untuk ketikan baru. */
interface Fetched {
  term: string;
  items: QuickPatient[];
  failed: boolean;
}

/** NIK (16 digit, boleh berspasi atau strip) atau nomor RM: pencarian yang hasilnya satu pasien tertentu. */
function isIdentifier(term: string): boolean {
  return NIK.test(term.replace(/[\s-]/g, '')) || MEDICAL_RECORD_NO.test(term);
}

/** Pilihan yang langsung dibuka: kecocokan persis nomor RM atau NIK, atau satu-satunya hasil. */
function pickDirect(term: string, found: QuickPatient[]): QuickPatient | null {
  const exact = found.find(
    (patient) =>
      patient.medical_record_no.toLowerCase() === term.toLowerCase() ||
      (patient.nik ?? '') === term.replace(/[\s-]/g, ''),
  );
  if (exact) {
    return exact;
  }
  return found.length === 1 && found[0] ? found[0] : null;
}

function openPatient(patient: QuickPatient) {
  router.visit(`/patients/${patient.id}`);
}

/**
 * PAS-02: kotak cari pasien cepat di bar jejak halaman. Nama menampilkan daftar; NIK atau nomor RM langsung membuka
 * halaman pasien. Kata kunci dikirim lewat POST, tidak pernah masuk URL atau riwayat peramban (M11).
 */
export function QuickPatientSearch() {
  const [text, setText] = useState('');
  const [open, setOpen] = useState(false);
  const [fetched, setFetched] = useState<Fetched | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [active, setActive] = useState(-1);
  const rootRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const listId = useId();
  const term = useDebouncedValue(text.trim(), 250);

  const close = useCallback(() => {
    setOpen(false);
    setActive(-1);
  }, []);
  useDismiss(rootRef, open, close);

  useEffect(() => {
    const onKey = (event: globalThis.KeyboardEvent) => {
      const target = event.target;
      const typing =
        target instanceof HTMLElement &&
        (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        inputRef.current?.focus();
      } else if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey) {
        event.preventDefault();
        inputRef.current?.focus();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('keydown', onKey);
    };
  }, []);

  // Nama: daftar muncul sambil mengetik. NIK dan nomor RM menunggu Enter agar tidak mencari di setiap digit.
  const eligible = term.length >= MIN_NAME_LENGTH && !isIdentifier(term);
  useEffect(() => {
    if (!eligible) {
      return;
    }
    const controller = new AbortController();
    quickSearchPatients(term, controller.signal)
      .then((items) => {
        setFetched({ term, items, failed: false });
        setActive(-1);
      })
      .catch((error: unknown) => {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
          setFetched({ term, items: [], failed: true });
        }
      });
    return () => {
      controller.abort();
    };
  }, [eligible, term]);

  const view = fetched && (fetched.term === term || fetched.term === text.trim()) ? fetched : null;
  const results = view?.items ?? [];
  const loading = submitting || (eligible && view === null);

  async function submit() {
    const query = text.trim();
    if (query === '') {
      return;
    }
    const highlighted = results[active];
    if (highlighted) {
      openPatient(highlighted);
      return;
    }
    setOpen(true);
    setSubmitting(true);
    try {
      const items = await quickSearchPatients(query);
      const direct = pickDirect(query, items);
      if (direct) {
        openPatient(direct);
        return;
      }
      setFetched({ term: query, items, failed: false });
    } catch {
      setFetched({ term: query, items: [], failed: true });
    } finally {
      setSubmitting(false);
    }
  }

  function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setOpen(true);
      setActive((index) => Math.min(index + 1, results.length - 1));
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActive((index) => Math.max(index - 1, -1));
    } else if (event.key === 'Enter') {
      event.preventDefault();
      void submit();
    } else if (event.key === 'Escape') {
      close();
      inputRef.current?.blur();
    }
  }

  const showNotFound = view !== null && !view.failed && results.length === 0 && !loading;
  const showPanel = open && text.trim().length >= MIN_NAME_LENGTH && (loading || view !== null);

  return (
    <div ref={rootRef} className="relative w-full sm:w-80 sm:shrink-0" role="search">
      <input
        ref={inputRef}
        type="search"
        role="combobox"
        aria-label="Pencarian pasien cepat"
        aria-expanded={showPanel}
        aria-controls={listId}
        aria-autocomplete="list"
        aria-activedescendant={active >= 0 ? `${listId}-${String(active)}` : undefined}
        autoComplete="off"
        placeholder="Cari pasien: nama, RM, atau NIK ( / )"
        value={text}
        onChange={(event) => {
          setText(event.target.value);
          setOpen(true);
        }}
        onFocus={() => {
          setOpen(true);
        }}
        onKeyDown={onKeyDown}
        className="field h-8 w-full py-0 text-sm"
      />
      {showPanel ? (
        <div className="border-line-soft absolute top-full right-0 z-30 mt-0.5 w-[min(28rem,calc(100vw-1rem))] rounded-xl border bg-white shadow-lg">
          <ul
            id={listId}
            role="listbox"
            aria-label="Hasil pencarian pasien"
            className="max-h-72 overflow-y-auto"
          >
            {results.map((patient, index) => (
              <li
                key={patient.id}
                id={`${listId}-${String(index)}`}
                role="option"
                aria-selected={index === active}
              >
                <button
                  type="button"
                  className={`w-full px-2 py-1.5 text-left text-xs ${index === active ? 'bg-brand-50' : 'hover:bg-brand-50'}`}
                  onClick={() => {
                    openPatient(patient);
                  }}
                >
                  <span className="block font-bold">{patient.name}</span>
                  <span className="text-muted block">
                    {patient.medical_record_no} · {formatDate(patient.birth_date)}
                    {patient.nik ? ` · NIK ${patient.nik}` : ''}
                  </span>
                </button>
              </li>
            ))}
          </ul>
          {loading ? (
            <p role="status" className="text-muted px-2 py-1.5 text-xs">
              Mencari…
            </p>
          ) : null}
          {showNotFound ? (
            <p role="status" className="text-muted px-2 py-1.5 text-xs">
              Pasien tidak ditemukan.
            </p>
          ) : null}
          {view?.failed ? (
            <p role="alert" className="text-danger-600 px-2 py-1.5 text-xs">
              Pencarian gagal. Coba lagi.
            </p>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
