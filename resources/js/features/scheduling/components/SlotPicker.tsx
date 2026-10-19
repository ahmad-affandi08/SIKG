import { EmptyState } from '@/components/ui/EmptyState';
import { useAvailability } from '@/features/scheduling/queries';

interface SlotPickerProps {
  doctor: string;
  date: string;
  minutes: number;
  chair: string;
  time: string;
  onPick: (time: string) => void;
}

/** Jam mulai yang masih kosong untuk dokter (dan kursi) pada tanggal itu. */
export function SlotPicker({ doctor, date, minutes, chair, time, onPick }: SlotPickerProps) {
  const ready = doctor !== '' && date !== '' && minutes >= 10;
  const slots = useAvailability({ doctor, date, minutes, chair }, ready);

  if (!ready) {
    return null;
  }
  return (
    <div className="flex flex-col gap-1">
      <span className="text-sm font-semibold">Jam kosong</span>
      {slots.isSuccess && slots.data.length === 0 ? (
        <EmptyState
          kind="calendar"
          size="compact"
          title="Tidak ada jam kosong pada tanggal itu"
          description="Coba tanggal atau dokter lain."
        />
      ) : null}
      <div className="flex flex-wrap gap-2">
        {(slots.data ?? []).map((slot) => (
          <button
            key={slot}
            type="button"
            aria-pressed={slot === time}
            onClick={() => {
              onPick(slot);
            }}
            className="btn btn-secondary"
          >
            {slot}
          </button>
        ))}
      </div>
    </div>
  );
}
