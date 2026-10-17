import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { NAV_ITEMS, visibleNavItems } from '@/components/layout/navigation';
import type { NavItem } from '@/components/layout/navigation';

interface AppLayoutProps {
  children: ReactNode;
  /** Untuk tes dan modul yang memperluas menu; bawaannya daftar menu aplikasi. */
  navItems?: readonly NavItem[];
}

export function AppLayout({ children, navItems = NAV_ITEMS }: AppLayoutProps) {
  const { auth, clinic } = usePage().props;
  const { url } = usePage();
  const items = visibleNavItems(navItems, auth.permissions);

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="flex flex-wrap items-center justify-between gap-3 bg-white px-6 py-3 shadow-sm">
        <span className="text-lg font-semibold">{clinic?.name ?? 'SIKG'}</span>
        <div className="flex items-center gap-3 text-sm">
          {auth.user ? <span>{auth.user.name}</span> : null}
          <Link
            href="/logout"
            method="post"
            as="button"
            className="min-h-touch rounded-control px-4 ring-1 ring-slate-300"
          >
            Keluar
          </Link>
        </div>
      </header>
      <nav aria-label="Menu utama" className="bg-white px-6 py-2 shadow-sm">
        <ul className="flex flex-wrap gap-2">
          {items.map((item) => (
            <li key={item.href}>
              <Link
                href={item.href}
                aria-current={url.startsWith(item.href) ? 'page' : undefined}
                className="min-h-touch rounded-control hover:bg-brand-50 aria-[current=page]:bg-brand-100 inline-flex items-center px-4 text-sm font-medium"
              >
                {item.label}
              </Link>
            </li>
          ))}
        </ul>
      </nav>
      <div>{children}</div>
    </div>
  );
}
