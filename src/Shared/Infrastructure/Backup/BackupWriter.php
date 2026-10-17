<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Backup;

use Src\Shared\Application\Backup\BackupFailed;

/**
 * Menulis cadangan terenkripsi bertahap: catatan JSON per baris, dikompres per blok, lalu dienkripsi dengan
 * XChaCha20-Poly1305 secretstream (terautentikasi; potongan atau perubahan terdeteksi saat dibaca). Format:
 * "SIKGBAK1" + header stream (24 byte) + bingkai [panjang 4 byte big-endian][ciphertext]; bingkai terakhir bertanda FINAL.
 */
final class BackupWriter
{
    public const MAGIC = 'SIKGBAK1';

    private const BLOCK_BYTES = 524288;

    private string $state;

    private string $buffer = '';

    private bool $closed = false;

    /**
     * @param  resource  $stream
     */
    public function __construct(private $stream, string $key)
    {
        [$this->state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $this->put(self::MAGIC.$header);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function write(array $record): void
    {
        $this->buffer .= json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n";
        if (strlen($this->buffer) >= self::BLOCK_BYTES) {
            $this->flush(false);
        }
    }

    public function close(): void
    {
        if (! $this->closed) {
            $this->flush(true);
            $this->closed = true;
        }
    }

    private function flush(bool $final): void
    {
        $plain = gzcompress($this->buffer, 6);
        if ($plain === false) {
            throw new BackupFailed('Kompresi blok cadangan gagal.');
        }
        $this->buffer = '';
        $cipher = sodium_crypto_secretstream_xchacha20poly1305_push(
            $this->state,
            $plain,
            '',
            $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE,
        );
        $this->put(pack('N', strlen($cipher)).$cipher);
    }

    private function put(string $bytes): void
    {
        if (fwrite($this->stream, $bytes) !== strlen($bytes)) {
            throw new BackupFailed('Penulisan berkas cadangan gagal (disk penuh atau tidak dapat ditulis).');
        }
    }
}
