import { forwardRef } from 'react';

interface PickerToggleProps {
  icon: 'calendar' | 'clock';
  label: string;
  expanded: boolean;
  disabled?: boolean | undefined;
  onClick: () => void;
}

/** Tombol pembuka panel pemilih; target sentuh 44 px. Ikon hanya hiasan, nama tombol lewat aria-label. */
export const PickerToggle = forwardRef<HTMLButtonElement, PickerToggleProps>(function PickerToggle(
  { icon, label, expanded, disabled, onClick },
  ref,
) {
  return (
    <button
      ref={ref}
      type="button"
      aria-label={label}
      aria-haspopup="dialog"
      aria-expanded={expanded}
      disabled={disabled === true}
      onClick={onClick}
      className="hover:bg-brand-100 absolute top-0 right-0 flex size-11 items-center justify-center disabled:opacity-40"
    >
      <svg
        viewBox="0 0 24 24"
        width="20"
        height="20"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.7"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
        focusable="false"
      >
        {icon === 'calendar' ? (
          <>
            <rect x="3" y="4.5" width="18" height="16.5" rx="0" />
            <path d="M3 10h18M8 2.5v4M16 2.5v4" />
          </>
        ) : (
          <>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
          </>
        )}
      </svg>
    </button>
  );
});
