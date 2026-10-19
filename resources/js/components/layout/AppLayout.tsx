import { Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';

import { buildCrumbs } from '@/components/layout/breadcrumbs';
import { FlashMessages } from '@/components/layout/FlashMessages';
import { LogoutButton } from '@/components/layout/LogoutButton';
import { NotificationBell } from '@/components/layout/NotificationBell';
import { OfflineIndicator } from '@/components/layout/OfflineIndicator';
import { QuickPatientSearch } from '@/components/layout/QuickPatientSearch';
import { NAV_ITEMS, visibleNavItems } from '@/components/layout/navigation';
import type { NavItem } from '@/components/layout/navigation';
import { PageTitleProvider } from '@/components/layout/PageTitle';
import { useDismiss } from '@/components/ui/useDismiss';
import { formatDateTime } from '@/lib/format';
import { RequiredProvider } from '@/lib/useRequired';

interface AppLayoutProps {
  children: ReactNode;
  /** Untuk tes dan modul yang memperluas menu; bawaannya daftar menu aplikasi. */
  navItems?: readonly NavItem[];
  /** Halaman yang menyusun panelnya sendiri (mis. Pemeriksaan) tidak dibungkus satu panel gulir. */
  bare?: boolean;
}

type Entry = { kind: 'link'; item: NavItem } | { kind: 'group'; title: string; items: NavItem[] };

/** Item tanpa kelompok menjadi menu langsung; item berkelompok dikumpulkan dalam satu menu turun. */
function toEntries(items: readonly NavItem[]): Entry[] {
  const entries: Entry[] = [];
  for (const item of items) {
    if (!item.group) {
      entries.push({ kind: 'link', item });
      continue;
    }
    const existing = entries.find(
      (entry): entry is Extract<Entry, { kind: 'group' }> =>
        entry.kind === 'group' && entry.title === item.group,
    );
    if (existing) {
      existing.items.push(item);
    } else {
      entries.push({ kind: 'group', title: item.group, items: [item] });
    }
  }
  return entries;
}

function roleText(role: string | undefined): string {
  return role ? role.charAt(0).toUpperCase() + role.slice(1) : '';
}

export function AppLayout({ children, navItems = NAV_ITEMS, bare = false }: AppLayoutProps) {
  return (
    <PageTitleProvider>
      {(title) => (
        <Shell navItems={navItems} pageTitle={title} bare={bare}>
          {children}
        </Shell>
      )}
    </PageTitleProvider>
  );
}

function Clock({ timezone }: { timezone: string | undefined }) {
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const timer = window.setInterval(() => {
      setNow(new Date());
    }, 1000);
    return () => {
      window.clearInterval(timer);
    };
  }, []);
  return <time dateTime={now.toISOString()}>{formatDateTime(now, timezone)}</time>;
}

function Shell({
  children,
  navItems,
  pageTitle,
  bare,
}: {
  children: ReactNode;
  navItems: readonly NavItem[];
  pageTitle: string | null;
  bare: boolean;
}) {
  const { auth, clinic, branch } = usePage().props;
  const { url } = usePage();
  const visible = visibleNavItems(navItems, auth.permissions);
  const entries = toEntries(visible);
  const crumbs = buildCrumbs(url, visible, pageTitle);
  const [openGroup, setOpenGroup] = useState<string | null>(null);
  const focusFirst = useRef(false);
  const barRef = useRef<HTMLElement>(null);
  const closeMenu = useCallback(() => {
    setOpenGroup(null);
  }, []);
  useDismiss(barRef, openGroup !== null, closeMenu);

  useEffect(() => {
    if (openGroup !== null && focusFirst.current) {
      barRef.current?.querySelector<HTMLElement>('[data-menu-open] a')?.focus();
    }
    focusFirst.current = false;
  }, [openGroup]);

  function onMenuKey(event: KeyboardEvent<HTMLElement>) {
    const links = Array.from(
      event.currentTarget.closest('ul')?.querySelectorAll<HTMLElement>('a') ?? [],
    );
    const index = links.indexOf(document.activeElement as HTMLElement);
    const step = { ArrowDown: 1, ArrowUp: -1 }[event.key];
    if (step !== undefined) {
      event.preventDefault();
      links[(index + step + links.length) % links.length]?.focus();
    }
  }

  return (
    <div className="flex h-dvh flex-col">
      <nav
        ref={barRef}
        aria-label="Menu utama"
        className="border-line-soft flex flex-wrap items-center gap-0.5 border-b bg-white px-2 py-1"
      >
        <span className="text-brand-700 px-2 text-base font-extrabold tracking-tight">SIKG</span>
        {entries.map((entry) => {
          if (entry.kind === 'link') {
            const active = url.startsWith(entry.item.href);
            return (
              <Link
                key={entry.item.href}
                href={entry.item.href}
                aria-current={active ? 'page' : undefined}
                onClick={closeMenu}
                className="menubar-item"
              >
                {entry.item.label}
              </Link>
            );
          }
          const open = openGroup === entry.title;
          const active = entry.items.some((item) => url.startsWith(item.href));
          return (
            <div key={entry.title} className="relative">
              <button
                type="button"
                className="menubar-item"
                data-active={active ? 'true' : undefined}
                aria-expanded={open}
                aria-controls={`menu-${entry.title}`}
                onClick={() => {
                  setOpenGroup(open ? null : entry.title);
                }}
                onMouseEnter={() => {
                  if (openGroup !== null) {
                    setOpenGroup(entry.title);
                  }
                }}
                onKeyDown={(event) => {
                  if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    setOpenGroup(entry.title);
                    focusFirst.current = true;
                  }
                }}
              >
                {entry.title}
                <span aria-hidden="true" className="ml-1 text-[9px]">
                  ▾
                </span>
              </button>
              {open ? (
                <ul
                  id={`menu-${entry.title}`}
                  data-menu-open
                  className="border-line-soft absolute top-full left-0 z-50 mt-1 min-w-52 rounded-xl border bg-white py-1 shadow-lg"
                >
                  {entry.items.map((item) => (
                    <li key={item.href}>
                      <Link
                        href={item.href}
                        aria-current={url.startsWith(item.href) ? 'page' : undefined}
                        onClick={closeMenu}
                        onKeyDown={onMenuKey}
                        className="menu-link"
                      >
                        {item.label}
                      </Link>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>
          );
        })}
        <span className="ml-auto flex items-center gap-px">
          {branch && !branch.locked && branch.options.length > 0 ? (
            <label className="flex items-center gap-1 px-1 text-xs">
              <span>Cabang</span>
              <select
                aria-label="Cabang aktif"
                className="field !min-h-0 !py-0.5 text-xs"
                value={branch.active_id ?? ''}
                onChange={(event) => {
                  router.post('/branch/switch', { branch_id: event.target.value || null });
                }}
              >
                <option value="">Semua cabang</option>
                {branch.options.map((option) => (
                  <option key={option.id} value={option.id}>
                    {option.name}
                  </option>
                ))}
              </select>
            </label>
          ) : null}
          {auth.user ? <NotificationBell onClick={closeMenu} /> : null}
          <LogoutButton onClick={closeMenu} />
        </span>
      </nav>

      <div className="flex flex-wrap items-center gap-x-2 px-3 pt-2 pb-0.5 sm:flex-nowrap">
        <nav aria-label="Jejak halaman" className="min-w-0 flex-1">
          <ol className="flex min-h-7 flex-wrap items-center gap-x-1.5 text-xs">
            {crumbs.map((crumb, index) => {
              const last = index === crumbs.length - 1;
              return (
                <li
                  key={`${crumb.label}-${String(index)}`}
                  className="flex min-w-0 items-center gap-1.5"
                >
                  {index > 0 ? (
                    <span aria-hidden="true" className="text-muted">
                      ›
                    </span>
                  ) : null}
                  {crumb.href && !last ? (
                    <Link href={crumb.href} className="text-brand-700 hover:underline">
                      {crumb.label}
                    </Link>
                  ) : (
                    <span
                      aria-current={last ? 'page' : undefined}
                      className={last ? 'truncate font-bold' : 'text-muted'}
                    >
                      {crumb.label}
                    </span>
                  )}
                </li>
              );
            })}
          </ol>
        </nav>
        {auth.user && auth.permissions.includes('patient.view') ? <QuickPatientSearch /> : null}
      </div>

      <div className="flex min-h-0 flex-1 flex-col gap-2 px-3 pt-1 pb-3">
        <FlashMessages />
        <RequiredProvider>
          {bare ? (
            <div className="flex min-h-0 flex-1 flex-col">{children}</div>
          ) : (
            <div className="pane min-h-0 flex-1 overflow-auto">{children}</div>
          )}
        </RequiredProvider>
      </div>

      <footer className="status-bar flex flex-wrap items-center gap-x-4 px-2 py-0.5 text-xs text-white">
        <span className="truncate">
          {clinic?.name ?? ''}
          {branch?.active_name ? ` - ${branch.active_name}` : ''}
        </span>
        <span aria-hidden="true" className="text-white/50">
          |
        </span>
        <span className="truncate">
          {auth.user ? <strong className="font-semibold">{auth.user.name}</strong> : null}
          {auth.user?.roles[0] ? ` (${roleText(auth.user.roles[0])})` : ''}
        </span>
        {auth.user ? <OfflineIndicator /> : null}
        <span className="ml-auto tabular-nums">
          <Clock timezone={clinic?.timezone} />
        </span>
      </footer>
    </div>
  );
}
