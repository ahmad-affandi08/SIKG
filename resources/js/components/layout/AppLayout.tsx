import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { FlashMessages } from '@/components/layout/FlashMessages';
import { NAV_ITEMS, visibleNavItems } from '@/components/layout/navigation';
import type { NavItem } from '@/components/layout/navigation';

interface AppLayoutProps {
  children: ReactNode;
  /** Untuk tes dan modul yang memperluas menu; bawaannya daftar menu aplikasi. */
  navItems?: readonly NavItem[];
}

interface Section {
  title: string | null;
  items: { item: NavItem; no: string }[];
}

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
    <div className="min-h-screen lg:pl-60">
      <aside
        id="sidebar"
        className={`border-ink fixed inset-y-0 left-0 z-40 flex w-60 flex-col overflow-y-auto border-r bg-white px-6 py-8 transition-transform duration-150 lg:translate-x-0 ${
          open ? 'translate-x-0' : '-translate-x-full'
        }`}
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
                          setOpen(false);
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

      {open ? (
        <button
          type="button"
          aria-label="Tutup menu"
          className="fixed inset-0 z-30 bg-black/40 lg:hidden"
          onClick={() => {
            setOpen(false);
          }}
        />
      ) : null}

      <header className="border-ink sticky top-0 z-20 flex items-center gap-4 border-b bg-white px-4 py-1 lg:hidden">
        <button
          type="button"
          aria-expanded={open}
          aria-controls="sidebar"
          className="btn btn-secondary min-h-touch px-4"
          onClick={() => {
            setOpen((value) => !value);
          }}
        >
          {open ? 'Tutup' : 'Menu'}
        </button>
        <span className="font-bold tracking-tight">SIKG</span>
      </header>

      <FlashMessages />
      <div>{children}</div>
    </div>
  );
}
