import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { buildCrumbs } from '@/components/layout/breadcrumbs';
import { FlashMessages } from '@/components/layout/FlashMessages';
import { NAV_ITEMS, visibleNavItems } from '@/components/layout/navigation';
import type { NavItem } from '@/components/layout/navigation';
import { PageTitleProvider } from '@/components/layout/PageTitle';
import { formatDate } from '@/lib/format';
import { useMediaQuery } from '@/lib/useMediaQuery';
import { RequiredProvider } from '@/lib/useRequired';

interface AppLayoutProps {
  children: ReactNode;
  /** Untuk tes dan modul yang memperluas menu; bawaannya daftar menu aplikasi. */
  navItems?: readonly NavItem[];
}

interface Section {
  title: string | null;
  items: { item: NavItem; no: string }[];
}

const COLLAPSE_COOKIE = 'sikg_sidebar_collapsed';

/** Item berurutan dengan kelompok yang sama digabung di bawah satu judul. */
function toSections(items: readonly NavItem[]): Section[] {
  const sections: Section[] = [];
  items.forEach((item, index) => {
    const title = item.group ?? null;
    const entry = { item, no: String(index + 1).padStart(2, '0') };
    const last = sections[sections.length - 1];
    if (last && last.title === title) {
      last.items.push(entry);
    } else {
      sections.push({ title, items: [entry] });
    }
  });
  return sections;
}

function roleText(role: string | undefined): string {
  return role ? role.charAt(0).toUpperCase() + role.slice(1) : '';
}

/**
 * Pilihan menutup sidebar diingat per peramban lewat cookie biasa sebagai kenyamanan tampilan (bukan data pasien;
 * penyimpanan peramban dilarang untuk PHI, M11).
 */
function readCollapsed(): boolean {
  return document.cookie.split('; ').includes(`${COLLAPSE_COOKIE}=1`);
}

function writeCollapsed(value: boolean) {
  document.cookie = `${COLLAPSE_COOKIE}=${value ? '1' : '0'}; path=/; max-age=31536000; SameSite=Lax`;
}

export function AppLayout({ children, navItems = NAV_ITEMS }: AppLayoutProps) {
  return (
    <PageTitleProvider>
      {(title) => (
        <Shell navItems={navItems} pageTitle={title}>
          {children}
        </Shell>
      )}
    </PageTitleProvider>
  );
}

function Shell({
  children,
  navItems,
  pageTitle,
}: {
  children: ReactNode;
  navItems: readonly NavItem[];
  pageTitle: string | null;
}) {
  const { auth, clinic } = usePage().props;
  const { url } = usePage();
  const desktop = useMediaQuery('(min-width: 1024px)');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(readCollapsed);
  const visible = visibleNavItems(navItems, auth.permissions);
  const sections = toSections(visible);
  const crumbs = buildCrumbs(url, visible, pageTitle);
  const hidden = desktop ? collapsed : !drawerOpen;

  useEffect(() => {
    if (!drawerOpen) {
      return;
    }
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setDrawerOpen(false);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => {
      window.removeEventListener('keydown', onKey);
    };
  }, [drawerOpen]);

  function toggle() {
    if (desktop) {
      setCollapsed((value) => {
        writeCollapsed(!value);
        return !value;
      });
    } else {
      setDrawerOpen((value) => !value);
    }
  }

  return (
    <div
      className={`min-h-screen transition-[padding] duration-150 ${collapsed ? '' : 'lg:pl-60'}`}
    >
      <aside
        id="sidebar"
        inert={hidden}
        className={`border-ink fixed inset-y-0 left-0 z-40 flex w-60 flex-col overflow-y-auto border-r bg-white px-6 py-8 transition-transform duration-150 ${
          drawerOpen ? 'translate-x-0' : '-translate-x-full'
        } ${collapsed ? 'lg:-translate-x-full' : 'lg:translate-x-0'}`}
      >
        <div>
          <p className="text-xl font-bold tracking-tight">SIKG</p>
          <p className="text-muted truncate text-xs">{clinic?.name ?? ''}</p>
        </div>

        <nav aria-label="Menu utama" className="mt-8 flex-1">
          {sections.map((section) => (
            <div key={section.title ?? 'utama'} className="mb-5">
              {section.title ? (
                <p className="eyebrow text-signal-700 pb-1">{section.title}</p>
              ) : null}
              <ul>
                {section.items.map(({ item, no }) => {
                  const active = url.startsWith(item.href);
                  return (
                    <li key={item.href}>
                      <Link
                        href={item.href}
                        aria-current={active ? 'page' : undefined}
                        onClick={() => {
                          setDrawerOpen(false);
                        }}
                        className="min-h-touch aria-[current=page]:text-signal-700 hover:text-signal-700 flex items-center border-t border-[#ddd] text-sm font-medium aria-[current=page]:font-bold"
                      >
                        <span aria-hidden="true" className="text-muted w-7 text-[11px] font-normal">
                          {no}
                        </span>
                        <span className="truncate">{item.label}</span>
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          ))}
        </nav>

        <div className="border-ink border-t pt-4 text-sm">
          {auth.user ? <p className="truncate font-semibold">{auth.user.name}</p> : null}
          <p className="text-muted truncate">{roleText(auth.user?.roles[0])}</p>
          <Link
            href="/logout"
            method="post"
            as="button"
            className="min-h-touch hover:text-signal-700 mt-1 text-left font-medium underline underline-offset-4"
          >
            Keluar
          </Link>
        </div>
      </aside>

      {drawerOpen ? (
        <button
          type="button"
          aria-label="Tutup menu"
          className="fixed inset-0 z-30 bg-black/40 lg:hidden"
          onClick={() => {
            setDrawerOpen(false);
          }}
        />
      ) : null}

      <header className="border-ink sticky top-0 z-20 flex items-center gap-3 border-b bg-white px-4 lg:px-8">
        <button
          type="button"
          aria-controls="sidebar"
          aria-expanded={!hidden}
          aria-label={hidden ? 'Buka menu samping' : 'Tutup menu samping'}
          title={hidden ? 'Buka menu samping' : 'Tutup menu samping'}
          className="hover:bg-brand-100 -ml-2 flex size-11 shrink-0 items-center justify-center"
          onClick={toggle}
        >
          <svg
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            aria-hidden="true"
            focusable="false"
          >
            <rect x="3" y="4.5" width="18" height="15" />
            <path d="M9 4.5v15" />
            {hidden ? <path d="m13 10 2 2-2 2" /> : <path d="m16 10-2 2 2 2" />}
          </svg>
        </button>
        <nav aria-label="Jejak halaman" className="min-w-0 flex-1">
          <ol className="flex min-h-14 flex-wrap items-center gap-x-2 text-sm">
            {crumbs.map((crumb, index) => {
              const last = index === crumbs.length - 1;
              return (
                <li
                  key={`${crumb.label}-${String(index)}`}
                  className="flex min-w-0 items-center gap-2"
                >
                  {index > 0 ? (
                    <span aria-hidden="true" className="text-[#999]">
                      /
                    </span>
                  ) : null}
                  {crumb.href && !last ? (
                    <Link
                      href={crumb.href}
                      className="text-muted hover:text-signal-700 underline-offset-4 hover:underline"
                    >
                      {crumb.label}
                    </Link>
                  ) : (
                    <span
                      aria-current={last ? 'page' : undefined}
                      className={last ? 'truncate font-semibold' : 'text-muted'}
                    >
                      {crumb.label}
                    </span>
                  )}
                </li>
              );
            })}
          </ol>
        </nav>
        <p className="text-muted hidden shrink-0 text-sm tabular-nums sm:block">
          {formatDate(new Date(), clinic?.timezone)}
        </p>
      </header>

      <FlashMessages />
      <RequiredProvider>
        <div>{children}</div>
      </RequiredProvider>
    </div>
  );
}
