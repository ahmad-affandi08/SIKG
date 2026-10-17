import type { ReactNode } from 'react';

export type IconName =
  | 'home'
  | 'patients'
  | 'calendar'
  | 'queue'
  | 'pill'
  | 'users'
  | 'building'
  | 'cog'
  | 'doctor'
  | 'chair'
  | 'tooth'
  | 'tag'
  | 'card'
  | 'shield'
  | 'logout'
  | 'menu'
  | 'close';

const PATHS: Record<IconName, ReactNode> = {
  home: <path d="M3 10.5 12 3l9 7.5M5 9.5V21h14V9.5M10 21v-6h4v6" />,
  patients: (
    <>
      <circle cx="9" cy="8" r="3.5" />
      <path d="M2.5 20c.6-3.3 3.200-5 6.500-5s5.900 1.700 6.500 5" />
      <path d="M17 11.500a3 3 0 1 0 0-6M18.500 15.200c1.800.6 2.800 2.200 3 4.800" />
    </>
  ),
  calendar: (
    <>
      <rect x="3" y="4.500" width="18" height="16.500" rx="2.500" />
      <path d="M3 10h18M8 2.500v4M16 2.500v4" />
    </>
  ),
  queue: <path d="M8 6h13M8 12h13M8 18h13M3.500 6h.01M3.500 12h.01M3.500 18h.01" />,
  pill: (
    <>
      <rect x="2.500" y="8" width="19" height="8" rx="4" transform="rotate(-35 12 12)" />
      <path d="m8.500 8.500 7 7" />
    </>
  ),
  users: (
    <>
      <circle cx="12" cy="8" r="4" />
      <path d="M4 21c.6-4 3.700-6 8-6s7.400 2 8 6" />
    </>
  ),
  building: (
    <path d="M4 21V5.500L12 3l8 2.500V21M2.500 21h19M9 9h.01M15 9h.01M9 13h.01M15 13h.01M10 21v-4h4v4" />
  ),
  cog: (
    <>
      <circle cx="12" cy="12" r="3" />
      <path d="M19.400 15a1.700 1.700 0 0 0 .3 1.800l.1.100a2 2 0 1 1-2.800 2.800l-.1-.1a1.700 1.700 0 0 0-1.800-.3 1.700 1.700 0 0 0-1 1.500V21a2 2 0 1 1-4 0v-.1a1.700 1.700 0 0 0-1.100-1.500 1.700 1.700 0 0 0-1.800.3l-.1.100a2 2 0 1 1-2.800-2.800l.1-.1a1.700 1.700 0 0 0 .3-1.800 1.700 1.700 0 0 0-1.500-1H3a2 2 0 1 1 0-4h.1a1.700 1.700 0 0 0 1.500-1.100 1.700 1.700 0 0 0-.3-1.800l-.1-.1a2 2 0 1 1 2.800-2.800l.1.100a1.700 1.700 0 0 0 1.800.3H9a1.700 1.700 0 0 0 1-1.500V3a2 2 0 1 1 4 0v.1a1.700 1.700 0 0 0 1 1.500 1.700 1.700 0 0 0 1.800-.3l.1-.1a2 2 0 1 1 2.800 2.800l-.1.100a1.700 1.700 0 0 0-.3 1.800V9a1.700 1.700 0 0 0 1.500 1H21a2 2 0 1 1 0 4h-.1a1.700 1.700 0 0 0-1.500 1z" />
    </>
  ),
  doctor: (
    <>
      <circle cx="12" cy="7.500" r="3.500" />
      <path d="M5 21v-1.500A4.500 4.500 0 0 1 9.500 15h5a4.500 4.500 0 0 1 4.500 4.500V21M12 15v4M10 17h4" />
    </>
  ),
  chair: <path d="M7 3h6a2 2 0 0 1 2 2v7H7zM5 12h14v3H5zM9 15v6M15 15v6M7 21h10" />,
  tooth: (
    <path d="M7.500 3.500c-2.200 0-4 1.600-4 4 0 2 .8 3.300 1.600 5 .8 1.700.9 4 1.400 6 .3 1.100 1 1.600 1.800 1.600 1.100 0 1.300-1.300 1.600-2.700.2-1 .6-1.800 1.600-1.800s1.400.8 1.600 1.800c.3 1.400.5 2.700 1.600 2.700.8 0 1.500-.5 1.800-1.600.5-2 .6-4.300 1.400-6 .8-1.700 1.600-3 1.600-5 0-2.400-1.800-4-4-4-1.400 0-2.200.5-3 .9-.6.300-1.200.3-1.800 0-.800-.400-1.600-.900-3-.900z" />
  ),
  tag: (
    <>
      <path d="M3 12.500V4a1 1 0 0 1 1-1h8.500l8.300 8.300a1.500 1.500 0 0 1 0 2.100l-6.400 6.400a1.500 1.500 0 0 1-2.100 0z" />
      <circle cx="7.500" cy="7.500" r="1.200" />
    </>
  ),
  card: (
    <>
      <rect x="2.500" y="5" width="19" height="14" rx="2.500" />
      <path d="M2.500 10h19M6.500 15h4" />
    </>
  ),
  shield: (
    <path d="M12 3 4.500 6v5.500c0 4.600 3.100 8.200 7.500 9.500 4.400-1.300 7.500-4.900 7.500-9.500V6zM9 12l2.200 2.200L15.500 10" />
  ),
  logout: (
    <path d="M9 21H5.500A2.500 2.500 0 0 1 3 18.500v-13A2.500 2.500 0 0 1 5.500 3H9M16 17l5-5-5-5M21 12H9" />
  ),
  menu: <path d="M4 6.500h16M4 12h16M4 17.500h16" />,
  close: <path d="M6 6l12 12M18 6 6 18" />,
};

interface IconProps {
  name: IconName;
  className?: string;
}

/** Ikon garis bawaan (tanpa pustaka ikon). Dekoratif: disembunyikan dari pembaca layar. */
export function Icon({ name, className = 'size-5' }: IconProps) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.7"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
      className={className}
    >
      {PATHS[name]}
    </svg>
  );
}
