import type { NavItem } from '@/components/layout/navigation';
import { NAV_ALIASES } from '@/components/layout/navigation';

export interface Crumb {
  label: string;
  /** Kosong pada ruas terakhir (halaman sekarang) dan pada judul kelompok. */
  href?: string;
}

function pathOf(url: string): string {
  return url.split('?')[0]?.split('#')[0] ?? url;
}

function owner(path: string, items: readonly NavItem[]): NavItem | null {
  let best: NavItem | null = null;
  for (const item of items) {
    const under = path === item.href || path.startsWith(`${item.href}/`);
    if (under && (best === null || item.href.length > best.href.length)) {
      best = item;
    }
  }
  return best;
}

/**
 * Jejak halaman dari URL: Beranda, kelompok menu, menu pemilik halaman, lalu judul halaman bila berbeda dari menunya.
 * Halaman yang bukan menu sendiri (janji, kunjungan, dan sebagainya) dikaitkan ke menu induknya lewat NAV_ALIASES.
 */
export function buildCrumbs(
  url: string,
  items: readonly NavItem[],
  pageTitle: string | null,
  aliases: Readonly<Record<string, string>> = NAV_ALIASES,
): Crumb[] {
  const path = pathOf(url);
  const home = items.find((item) => item.href === '/dashboard');
  const crumbs: Crumb[] = [{ label: home?.label ?? 'Beranda', href: '/dashboard' }];
  if (path === '/dashboard') {
    return [{ label: crumbs[0]?.label ?? 'Beranda' }];
  }

  const aliasKey = Object.keys(aliases)
    .filter((prefix) => path === prefix || path.startsWith(`${prefix}/`))
    .sort((a, b) => b.length - a.length)[0];
  const resolved = aliasKey ? (aliases[aliasKey] ?? path) : path;
  const found = owner(
    resolved,
    items.filter((item) => item.href !== '/dashboard'),
  );

  if (found) {
    if (found.group) {
      crumbs.push({ label: found.group });
    }
    crumbs.push({ label: found.label, href: found.href });
    if (pageTitle && pageTitle !== found.label) {
      crumbs.push({ label: pageTitle });
    }
  } else if (pageTitle) {
    crumbs.push({ label: pageTitle });
  }

  const last = crumbs[crumbs.length - 1];
  if (last) {
    delete last.href;
  }
  return crumbs;
}
