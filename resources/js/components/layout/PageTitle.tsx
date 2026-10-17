import { Head } from '@inertiajs/react';
import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';

interface PageTitleValue {
  title: string | null;
  setTitle: (title: string | null) => void;
}

const PageTitleContext = createContext<PageTitleValue>({ title: null, setTitle: () => undefined });

/** Menyimpan judul halaman aktif agar header dapat memakainya sebagai ruas terakhir jejak halaman. */
export function PageTitleProvider({ children }: { children: (title: string | null) => ReactNode }) {
  const [title, setTitle] = useState<string | null>(null);
  const value = useMemo(() => ({ title, setTitle }), [title]);
  return <PageTitleContext.Provider value={value}>{children(title)}</PageTitleContext.Provider>;
}

/** Pengganti Head: mengatur judul tab peramban sekaligus ruas terakhir jejak halaman. */
export function PageHead({ title }: { title: string }) {
  const { setTitle } = useContext(PageTitleContext);
  useEffect(() => {
    setTitle(title);
    return () => {
      setTitle(null);
    };
  }, [title, setTitle]);
  return <Head title={title} />;
}
