import { useCallback, useEffect, useRef, useState } from 'react';
import type { PointerEvent } from 'react';

import { looksLikeSignature, MIN_POINT_DISTANCE } from '@/features/clinical/signature';

interface SignaturePadProps {
  label: string;
  /** Dipanggil dengan data URL PNG saat goresan selesai, atau teks kosong setelah dihapus. */
  onChange: (dataUrl: string) => void;
  error?: string | undefined;
}

const WIDTH = 600;
const HEIGHT = 200;

/**
 * Kanvas tanda tangan untuk tablet (sentuh, pena, atau tetikus). Gambar PNG transparan dikirim ke server yang memvalidasi
 * ulang. Layar sentuh tidak menggulir saat menggambar (`touch-action: none`).
 */
export function SignaturePad({ label, onChange, error }: SignaturePadProps) {
  const canvas = useRef<HTMLCanvasElement | null>(null);
  const points = useRef<{ x: number; y: number }[]>([]);
  const drawing = useRef(false);
  const [hasInk, setHasInk] = useState(false);

  useEffect(() => {
    const element = canvas.current;
    const context = element?.getContext('2d');
    if (!element || !context) {
      return;
    }
    context.lineWidth = 2.5;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.strokeStyle = '#111111';
  }, []);

  const position = useCallback((event: PointerEvent<HTMLCanvasElement>) => {
    const element = event.currentTarget;
    const rect = element.getBoundingClientRect();
    return {
      x: ((event.clientX - rect.left) / rect.width) * WIDTH,
      y: ((event.clientY - rect.top) / rect.height) * HEIGHT,
    };
  }, []);

  function start(event: PointerEvent<HTMLCanvasElement>) {
    const context = event.currentTarget.getContext('2d');
    if (!context) {
      return;
    }
    event.currentTarget.setPointerCapture(event.pointerId);
    drawing.current = true;
    const point = position(event);
    points.current.push(point);
    context.beginPath();
    context.moveTo(point.x, point.y);
  }

  function move(event: PointerEvent<HTMLCanvasElement>) {
    if (!drawing.current) {
      return;
    }
    const context = event.currentTarget.getContext('2d');
    const point = position(event);
    const last = points.current[points.current.length - 1];
    if (!context || (last && Math.hypot(point.x - last.x, point.y - last.y) < MIN_POINT_DISTANCE)) {
      return;
    }
    points.current.push(point);
    context.lineTo(point.x, point.y);
    context.stroke();
  }

  function end(event: PointerEvent<HTMLCanvasElement>) {
    if (!drawing.current) {
      return;
    }
    drawing.current = false;
    const ink = looksLikeSignature(points.current);
    setHasInk(ink);
    onChange(ink ? event.currentTarget.toDataURL('image/png') : '');
  }

  function clear() {
    const element = canvas.current;
    element?.getContext('2d')?.clearRect(0, 0, WIDTH, HEIGHT);
    points.current = [];
    setHasInk(false);
    onChange('');
  }

  return (
    <div className="flex flex-col gap-1">
      <span className="text-sm font-semibold">
        {label}
        <span aria-hidden="true" className="text-signal-700 ml-0.5">
          *
        </span>
      </span>
      <canvas
        ref={canvas}
        width={WIDTH}
        height={HEIGHT}
        role="img"
        aria-label={`${label}: kotak untuk menggambar tanda tangan dengan jari atau pena`}
        className="max-w-full touch-none rounded border border-[#b9b9b9] bg-white"
        style={{
          width: WIDTH,
          height: 'auto',
          aspectRatio: `${String(WIDTH)} / ${String(HEIGHT)}`,
        }}
        onPointerDown={start}
        onPointerMove={move}
        onPointerUp={end}
        onPointerCancel={end}
      />
      <div className="flex items-center gap-3">
        <button type="button" className="btn btn-secondary" onClick={clear} disabled={!hasInk}>
          Hapus tanda tangan
        </button>
        <span className="text-muted text-xs">
          {hasInk ? 'Tanda tangan terisi.' : 'Tanda tangan di kotak di atas.'}
        </span>
      </div>
      {error ? (
        <p role="alert" className="text-danger-600 text-sm">
          {error}
        </p>
      ) : null}
    </div>
  );
}
