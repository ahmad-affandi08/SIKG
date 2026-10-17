<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Gambar tanda tangan dari kanvas tablet: PNG dalam data URL base64. Divalidasi di server (tanda PNG, ukuran berkas, dimensi) agar
 * hanya gambar yang sah yang masuk ke PDF; hasilnya disematkan ke dokumen, bukan disajikan sebagai berkas lepas.
 */
final readonly class SignatureImage
{
    public const MAX_BYTES = 400_000;

    public const MIN_SIDE = 40;

    public const MAX_SIDE = 2400;

    private const PREFIX = 'data:image/png;base64,';

    private function __construct(public string $png) {}

    /**
     * @throws InvalidClinicalData
     */
    public static function fromDataUrl(string $dataUrl): self
    {
        if (! str_starts_with($dataUrl, self::PREFIX)) {
            throw new InvalidClinicalData('signature.invalid', 'Tanda tangan tidak valid; ulangi menandatangani di layar.');
        }
        $png = base64_decode(substr($dataUrl, strlen(self::PREFIX)), true);
        if ($png === false || strlen($png) < 33 || strlen($png) > self::MAX_BYTES || ! str_starts_with($png, "\x89PNG\r\n\x1a\n") || substr($png, 12, 4) !== 'IHDR') {
            throw new InvalidClinicalData('signature.invalid', 'Tanda tangan tidak valid; ulangi menandatangani di layar.');
        }
        $size = unpack('Nwidth/Nheight', substr($png, 16, 8));
        if ($size === false || $size['width'] < self::MIN_SIDE || $size['height'] < self::MIN_SIDE || $size['width'] > self::MAX_SIDE || $size['height'] > self::MAX_SIDE) {
            throw new InvalidClinicalData('signature.invalid', 'Ukuran gambar tanda tangan tidak sesuai; ulangi menandatangani di layar.');
        }

        return new self($png);
    }

    public function dataUrl(): string
    {
        return self::PREFIX.base64_encode($this->png);
    }
}
