import { BRAND } from '@/lib/brand';

export interface AuthBrandData {
  name: string;
  logoUrl: string | null;
}

/** Identitas klinik di atas formulir masuk. Tanpa data klinik (belum ada atau lebih dari satu), tampil ikon aplikasi. */
export function AuthBrand({ brand }: { brand?: AuthBrandData | null }) {
  const logoUrl = brand?.logoUrl ?? null;

  return (
    <div className="flex flex-col items-center gap-2 text-center" data-testid="auth-brand">
      {logoUrl ? (
        <img
          src={logoUrl}
          alt={`Logo ${brand?.name ?? 'klinik'}`}
          className="h-20 w-auto max-w-56 object-contain"
        />
      ) : (
        <img
          src="/icons/icon-192.png"
          alt={`Logo ${BRAND.name}`}
          className="h-16 w-16 rounded-xl"
        />
      )}
      {brand ? <p className="text-ink text-base leading-tight font-bold">{brand.name}</p> : null}
    </div>
  );
}
