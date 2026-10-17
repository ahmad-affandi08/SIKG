import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { FlashMessages } from '@/components/layout/FlashMessages';
import { Icon } from '@/components/layout/icons';
import { NAV_ITEMS, visibleNavItems } from '@/components/layout/navigation';
import type { NavItem } from '@/components/layout/navigation';

interface AppLayoutProps {
  children: ReactNode;
  /** Untuk tes dan modul yang memperluas menu; bawaannya daftar menu aplikasi. */
  navItems?: readonly NavItem[];
}

interface Section {
  title: string | null;
  items: NavItem[];
}

/** Item berurutan dengan kelompok yang sama digabung di bawah satu judul. */
function toSections(items: readonly NavItem[]): Section[] {
  const sections: Section[] = [];
  for (const item of items) {
    const title = item.group ?? null;
    const last = sections[sections.length - 1];
    if (last && last.title === title) {
      last.items.push(item);
    } else {
      sections.push({ title, items: [item] });
    }
  }
  return sections;
}

function initials(name: string): string {
  const letters = name
    .split(/\s+/)
    .filter((part) => part !== '')
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase());
  return letters.join('') || '?';
}

function roleText(role: string | undefined): string {
  return role ? role.charAt(0).toUpperCase() + role.slice(1) : '';
}

export function AppLayout({ children, navItems = NAV_ITEMS }: AppLayoutProps) {
  const { auth, clinic } = usePage().props;
  const { url } = usePage();
  const [open, setOpen] = useState(false);
  const sections = toSections(visibleNavItems(navItems, auth.permissions));

  useEffect(() => {
    if (!open) {
      return;
    }
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => {
      window.removeEventListener('keydown', onKey);
    };
  }, [open]);

  return (
    <div className="min-h-screen lg:pl-64">
      <aside
        id="sidebar"
        className={`bg-ink fixed inset-y-0 left-0 z-40 flex w-64 flex-col text-slate-300 shadow-xl transition-transform duration-200 lg:translate-x-0 lg:shadow-none ${
          open ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center gap-3 px-5 py-5">
          <span
            aria-hidden="true"
            className="bg-brand-600 flex size-9 shrink-0 items-center justify-center rounded-lg text-white"
          >
            <Icon name="tooth" className="size-5" />
          </span>
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-white">{clinic?.name ?? 'SIKG'}</p>
            <p className="text-xs text-slate-400">Sistem Informasi Klinik Gigi</p>
          </div>
        </div>

        <nav aria-label="Menu utama" className="flex-1 overflow-y-auto px-3 pb-4">
          {sections.map((section) => (
            <div key={section.title ?? 'utama'} className="mt-4 first:mt-0">
              {section.title ? (
                <p className="px-3 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                  {section.title}
                </p>
              ) : null}
              <ul className="flex flex-col gap-0.5">
                {section.items.map((item) => {
                  const active = url.startsWith(item.href);
                  return (
                    <li key={item.href}>
                      <Link
                        href={item.href}
                        aria-current={active ? 'page' : undefined}
                        onClick={() => {
                          setOpen(false);
                        }}
                        className="min-h-touch aria-[current=page]:bg-brand-600 flex items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-300 transition-colors hover:bg-white/10 hover:text-white aria-[current=page]:text-white"
                      >
                        {item.icon ? (
                          <Icon name={item.icon} className="size-[18px] shrink-0" />
                        ) : null}
                        <span className="truncate">{item.label}</span>
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          ))}
        </nav>

        <div className="border-t border-white/10 p-3">
          <div className="flex items-center gap-3 px-2 pb-3">
            <span
              aria-hidden="true"
              className="flex size-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-semibold text-white"
            >
              {initials(auth.user?.name ?? '')}
            </span>
            <div className="min-w-0">
              {auth.user ? (
                <p className="truncate text-sm font-medium text-white">{auth.user.name}</p>
              ) : null}
              <p className="truncate text-xs text-slate-400">{roleText(auth.user?.roles[0])}</p>
            </div>
          </div>
          <Link
            href="/logout"
            method="post"
            as="button"
            className="min-h-touch flex w-full items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-300 transition-colors hover:bg-white/10 hover:text-white"
          >
            <Icon name="logout" className="size-[18px]" />
            Keluar
          </Link>
        </div>
      </aside>

      {open ? (
        <button
          type="button"
          aria-label="Tutup menu"
          className="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
          onClick={() => {
            setOpen(false);
          }}
        />
      ) : null}

      <header className="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white/90 px-4 py-2 backdrop-blur lg:hidden">
        <button
          type="button"
          aria-label="Buka menu"
          aria-expanded={open}
          aria-controls="sidebar"
          className="btn btn-secondary px-3"
          onClick={() => {
            setOpen((value) => !value);
          }}
        >
          <Icon name={open ? 'close' : 'menu'} />
        </button>
        <span className="text-brand-700 font-semibold">SIKG</span>
      </header>

      <FlashMessages />
      <div>{children}</div>
    </div>
  );
}
