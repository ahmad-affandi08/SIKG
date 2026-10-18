<?php

declare(strict_types=1);

namespace Src\Clinical\Domain\Imaging;

use Src\Clinical\Domain\InvalidClinicalData;

/**
 * Pembaca DICOM Part 10 seperlunya (P3-03), murni PHP tanpa pustaka: header 128 byte + "DICM", File Meta (explicit VR little endian),
 * lalu dataset implicit VR little endian, explicit VR little endian, atau explicit VR big endian. Hanya tag yang dibutuhkan untuk
 * menampilkan gambar yang dibaca; urutan (SQ) dilewati termasuk yang berpanjang tak tentu. Format terkompresi (JPEG, RLE, dan sebagainya)
 * dan piksel selain skala abu satu sampel 8 atau 16 bit dilaporkan tidak didukung alih-alih ditebak.
 */
final class DicomReader
{
    private const IMPLICIT_LE = '1.2.840.10008.1.2';

    private const EXPLICIT_LE = '1.2.840.10008.1.2.1';

    private const EXPLICIT_BE = '1.2.840.10008.1.2.2';

    private const LONG_VR = ['OB', 'OD', 'OF', 'OL', 'OW', 'SQ', 'UC', 'UN', 'UR', 'UT'];

    private const UNDEFINED = 0xFFFFFFFF;

    /** Kedalaman urutan bersarang yang dilewati; lebih dari ini berkas dianggap rusak (mencegah rekursi tak terbatas). */
    private const MAX_NESTING = 16;

    /** @var array<string, int|string|null> */
    private array $tags = [];

    private int $pos = 0;

    private bool $explicit = true;

    private bool $big = false;

    private ?int $pixelOffset = null;

    private ?int $pixelLength = null;

    private function __construct(private readonly string $data) {}

    /**
     * @throws InvalidClinicalData
     */
    public static function read(string $data): DicomImage
    {
        if (strlen($data) < 140 || substr($data, 128, 4) !== 'DICM') {
            throw new InvalidClinicalData('clinical.dicom_invalid', 'Berkas bukan DICOM yang sah.');
        }
        $reader = new self($data);
        $reader->pos = 132;

        return $reader->parse();
    }

    private function parse(): DicomImage
    {
        $syntax = self::EXPLICIT_LE;
        // File Meta Information: selalu explicit VR little endian.
        while ($this->pos + 8 <= strlen($this->data)) {
            $group = $this->u16($this->pos, false);
            if ($group !== 0x0002) {
                break;
            }
            [$tag, $value, $next] = $this->element(true, false);
            if ($tag === '00020010') {
                $syntax = rtrim((string) $value, "\0 ");
            }
            $this->pos = $next;
        }

        [$this->explicit, $this->big, $reason] = match ($syntax) {
            self::IMPLICIT_LE => [false, false, null],
            self::EXPLICIT_LE => [true, false, null],
            self::EXPLICIT_BE => [true, true, null],
            default => [true, false, 'Gambar DICOM terkompresi (transfer syntax '.$syntax.') belum didukung. Ekspor DICOM tanpa kompresi, atau simpan gambar sebagai PNG atau JPG.'],
        };

        $this->scanDataset();

        $rows = (int) ($this->tags['00280010'] ?? 0);
        $columns = (int) ($this->tags['00280011'] ?? 0);
        $bits = (int) ($this->tags['00280100'] ?? 0);
        $samples = (int) ($this->tags['00280002'] ?? 1);
        $photometric = strtoupper(trim((string) ($this->tags['00280004'] ?? '')));

        if ($reason === null) {
            $reason = match (true) {
                $this->pixelOffset === null => 'Berkas DICOM tidak memuat data piksel.',
                $this->pixelLength === null || $this->pixelLength === self::UNDEFINED => 'Data piksel DICOM terkompresi (terbungkus) belum didukung.',
                $rows < 1 || $columns < 1 => 'Ukuran gambar DICOM tidak terbaca.',
                $rows * $columns > DicomImage::MAX_PIXELS => 'Gambar DICOM terlalu besar untuk ditampilkan.',
                $samples !== 1 || ! in_array($photometric, ['MONOCHROME1', 'MONOCHROME2'], true) => 'Hanya gambar DICOM skala abu satu kanal yang didukung.',
                ! in_array($bits, [8, 16], true) => 'Hanya piksel DICOM 8 atau 16 bit yang didukung.',
                $this->pixelLength < $rows * $columns * intdiv($bits, 8) => 'Data piksel DICOM lebih pendek dari ukuran gambar.',
                default => null,
            };
        }

        $pixels = $reason === null ? substr($this->data, (int) $this->pixelOffset, $rows * $columns * intdiv($bits, 8)) : '';
        $center = $this->firstNumber($this->tags['00281050'] ?? null);
        $width = $this->firstNumber($this->tags['00281051'] ?? null);
        $slope = $this->firstNumber($this->tags['00281053'] ?? null);

        return new DicomImage(
            $this->text($this->tags['00080060'] ?? null),
            $this->text($this->tags['00080020'] ?? null),
            $this->text($this->tags['00100020'] ?? null),
            $rows,
            $columns,
            $bits,
            ((int) ($this->tags['00280103'] ?? 0)) === 1,
            $photometric === 'MONOCHROME1',
            $slope ?? 1.0,
            $this->firstNumber($this->tags['00281052'] ?? null) ?? 0.0,
            $center,
            $width,
            $pixels,
            $reason,
            $this->big,
        );
    }

    /** Membaca elemen dataset utama sampai data piksel; tag yang dibutuhkan disimpan. */
    private function scanDataset(): void
    {
        $length = strlen($this->data);
        while ($this->pos + 8 <= $length) {
            [$tag, $value, $next, $valueStart, $valueLength] = $this->element($this->explicit, $this->big);
            if ($tag === '7FE00010') {
                $this->pixelOffset = $valueStart;
                $this->pixelLength = $valueLength;

                return;
            }
            if (in_array($tag, ['00080020', '00080060', '00100020', '00280002', '00280004', '00280010', '00280011', '00280100', '00280103', '00281050', '00281051', '00281052', '00281053'], true)) {
                $this->tags[$tag] = $value;
            }
            if ($next <= $this->pos) {
                return;
            }
            $this->pos = $next;
        }
    }

    /**
     * Membaca satu elemen pada posisi saat ini.
     *
     * @return array{0: string, 1: int|string|null, 2: int, 3: int, 4: int} tag (8 heksa huruf besar), nilai, posisi elemen berikutnya, awal nilai, panjang nilai
     */
    private function element(bool $explicit, bool $big): array
    {
        $length = strlen($this->data);
        $group = $this->u16($this->pos, $big);
        $element = $this->u16($this->pos + 2, $big);
        $tag = sprintf('%04X%04X', $group, $element);
        $vr = null;
        $cursor = $this->pos + 4;

        if ($explicit) {
            $vr = substr($this->data, $cursor, 2);
            if (in_array($vr, self::LONG_VR, true)) {
                $valueLength = $this->u32($cursor + 4, $big);
                $cursor += 8;
            } else {
                $valueLength = $this->u16($cursor + 2, $big);
                $cursor += 4;
            }
        } else {
            $valueLength = $this->u32($cursor, $big);
            $cursor += 4;
        }

        if ($valueLength === self::UNDEFINED) {
            if ($tag === '7FE00010') {
                return [$tag, null, $length, $cursor, self::UNDEFINED];
            }
            $end = $this->skipUndefined($cursor, $explicit, $big);

            return [$tag, null, $end, $cursor, 0];
        }
        if ($cursor + $valueLength > $length) {
            if ($tag === '7FE00010') {
                return [$tag, null, $length, $cursor, $length - $cursor];
            }
            throw new InvalidClinicalData('clinical.dicom_invalid', 'Berkas DICOM terpotong atau rusak.');
        }

        return [$tag, $this->value($tag, $vr, $cursor, $valueLength, $big), $cursor + $valueLength, $cursor, $valueLength];
    }

    private function value(string $tag, ?string $vr, int $start, int $length, bool $big): int|string|null
    {
        if ($tag === '7FE00010') {
            return null;
        }
        $isNumeric = in_array($tag, ['00280010', '00280011', '00280100', '00280002', '00280103'], true);
        if ($isNumeric && $length === 2) {
            return $this->u16($start, $big);
        }
        if ($tag === '00020010' || $length <= 256) {
            return substr($this->data, $start, $length);
        }

        return null;
    }

    /** Melewati urutan atau item berpanjang tak tentu sampai pembatasnya; mengembalikan posisi sesudah pembatas. */
    private function skipUndefined(int $cursor, bool $explicit, bool $big, int $depth = 0): int
    {
        $length = strlen($this->data);
        if ($depth > self::MAX_NESTING) {
            return $length;
        }
        while ($cursor + 8 <= $length) {
            $group = $this->u16($cursor, $big);
            $element = $this->u16($cursor + 2, $big);
            $itemLength = $this->u32($cursor + 4, $big);
            if ($group === 0xFFFE && $element === 0xE0DD) {
                return $cursor + 8;
            }
            if ($group === 0xFFFE && $element === 0xE000) {
                $cursor += 8;
                if ($itemLength === self::UNDEFINED) {
                    $cursor = $this->skipItemBody($cursor, $explicit, $big, $depth + 1);
                } else {
                    $cursor += $itemLength;
                }

                continue;
            }

            return $length;
        }

        return $length;
    }

    private function skipItemBody(int $cursor, bool $explicit, bool $big, int $depth): int
    {
        $length = strlen($this->data);
        while ($cursor + 8 <= $length) {
            $group = $this->u16($cursor, $big);
            $element = $this->u16($cursor + 2, $big);
            if ($group === 0xFFFE && $element === 0xE00D) {
                return $cursor + 8;
            }
            $inner = $cursor + 4;
            if ($explicit) {
                $vr = substr($this->data, $inner, 2);
                if (in_array($vr, self::LONG_VR, true)) {
                    $valueLength = $this->u32($inner + 4, $big);
                    $inner += 8;
                } else {
                    $valueLength = $this->u16($inner + 2, $big);
                    $inner += 4;
                }
            } else {
                $valueLength = $this->u32($inner, $big);
                $inner += 4;
            }
            $cursor = $valueLength === self::UNDEFINED ? $this->skipUndefined($inner, $explicit, $big, $depth + 1) : $inner + $valueLength;
        }

        return $length;
    }

    private function u16(int $at, bool $big): int
    {
        $chunk = substr($this->data, $at, 2);
        if (strlen($chunk) < 2) {
            return 0;
        }

        return (int) (unpack($big ? 'n' : 'v', $chunk)[1] ?? 0);
    }

    private function u32(int $at, bool $big): int
    {
        $chunk = substr($this->data, $at, 4);
        if (strlen($chunk) < 4) {
            return 0;
        }

        return (int) (unpack($big ? 'N' : 'V', $chunk)[1] ?? 0);
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $clean = trim($value, " \0");

        return $clean === '' ? null : $clean;
    }

    /** Angka pertama dari string DS bernilai ganda ("40\80"). */
    private function firstNumber(mixed $value): ?float
    {
        $text = $this->text($value);
        if ($text === null) {
            return null;
        }
        $first = trim(explode('\\', $text)[0]);

        return is_numeric($first) ? $first + 0.0 : null;
    }
}
