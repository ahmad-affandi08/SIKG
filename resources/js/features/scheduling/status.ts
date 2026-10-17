export type BadgeTone = 'neutral' | 'success' | 'danger' | 'warning';

/** Status tidak hanya lewat warna: label teks selalu ditampilkan di samping (aturan aksesibilitas frontend). */
export function statusTone(status: string): BadgeTone {
  switch (status) {
    case 'completed':
      return 'success';
    case 'cancelled':
    case 'no_show':
      return 'danger';
    case 'checked_in':
    case 'in_progress':
      return 'warning';
    default:
      return 'neutral';
  }
}
