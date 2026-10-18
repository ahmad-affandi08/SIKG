<?php

declare(strict_types=1);

namespace Src\Clinical\Domain\Imaging;

use Src\Clinical\Domain\InvalidClinicalData;

/**
 * Gambar DICOM hasil baca (P3-03): hanya metadata teknis yang dibutuhkan untuk menampilkan, tanpa nama atau data demografis pasien.
 * `patientId` (tag 0010,0020) dipakai sekali untuk mencocokkan nomor RM lalu dibuang. Piksel dikonversi ke skala abu 8-bit dengan
 * windowing (WindowCenter/Width bila ada, selain itu rentang nilai gambar), MONOCHROME1 dibalik. Hanya piksel tak terkompresi.
 */
final readonly class DicomImage
{
    public const MAX_PIXELS = 40_000_000;

    public function __construct(
        public ?string $modality,
        public ?string $studyDate,
        public ?string $patientId,
        public int $rows,
        public int $columns,
        public int $bitsAllocated,
        public bool $signed,
        public bool $inverted,
        public float $slope,
        public float $intercept,
        public ?float $windowCenter,
        public ?float $windowWidth,
        private string $pixels,
        public ?string $unsupportedReason,
        private bool $bigEndian = false,
    ) {}

    public function renderable(): bool
    {
        return $this->unsupportedReason === null;
    }

    /**
     * Piksel abu 8-bit baris demi baris (rows x columns byte).
     *
     * @throws InvalidClinicalData
     */
    public function toGray8(): string
    {
        if (! $this->renderable()) {
            throw new InvalidClinicalData('clinical.dicom_unsupported', (string) $this->unsupportedReason);
        }
        $bytes = intdiv($this->bitsAllocated, 8);
        $rowBytes = $this->columns * $bytes;
        [$low, $high] = $this->window($rowBytes, $bytes);
        $span = max($high - $low, 1.0);

        $out = '';
        for ($r = 0; $r < $this->rows; $r++) {
            $values = $this->rowValues(substr($this->pixels, $r * $rowBytes, $rowBytes), $bytes);
            $line = [];
            foreach ($values as $v) {
                $scaled = ($v * $this->slope + $this->intercept - $low) / $span * 255.0;
                $gray = (int) max(0, min(255, floor($scaled + 0.5)));
                $line[] = $this->inverted ? 255 - $gray : $gray;
            }
            $out .= pack('C*', ...$line);
        }

        return $out;
    }

    /**
     * @return array{0: float, 1: float} batas bawah dan atas nilai (sesudah slope dan intercept)
     */
    private function window(int $rowBytes, int $bytes): array
    {
        if ($this->windowCenter !== null && $this->windowWidth !== null && $this->windowWidth > 0) {
            return [$this->windowCenter - $this->windowWidth / 2, $this->windowCenter + $this->windowWidth / 2];
        }
        $min = null;
        $max = null;
        for ($r = 0; $r < $this->rows; $r++) {
            foreach ($this->rowValues(substr($this->pixels, $r * $rowBytes, $rowBytes), $bytes) as $v) {
                $value = $v * $this->slope + $this->intercept;
                $min = $min === null || $value < $min ? $value : $min;
                $max = $max === null || $value > $max ? $value : $max;
            }
        }

        return [($min ?? 0) + 0.0, ($max ?? 1) + 0.0];
    }

    /**
     * @return list<int>
     */
    private function rowValues(string $row, int $bytes): array
    {
        if ($bytes === 1) {
            /** @var list<int> $values */
            $values = array_values(unpack('C*', $row) ?: []);

            return $this->signed ? array_map(fn (int $v) => $v > 127 ? $v - 256 : $v, $values) : $values;
        }
        /** @var list<int> $values */
        $values = array_values(unpack($this->bigEndian ? 'n*' : 'v*', $row) ?: []);

        return $this->signed ? array_map(fn (int $v) => $v > 32767 ? $v - 65536 : $v, $values) : $values;
    }
}
