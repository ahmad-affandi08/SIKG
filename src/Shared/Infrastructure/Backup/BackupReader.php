<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Backup;

use Generator;
use JsonException;
use Src\Shared\Application\Backup\BackupFailed;

/**
 * Membaca cadangan buatan BackupWriter. Kunci salah, isi diubah, atau berkas terpotong menghasilkan BackupFailed.
 */
final class BackupReader
{
    /** @var resource */
    private $stream;

    /**
     * @param  resource  $stream
     */
    public function __construct($stream, private readonly string $key)
    {
        $this->stream = $stream;
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function records(): Generator
    {
        $head = $this->take(strlen(BackupWriter::MAGIC) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES, 'Berkas bukan cadangan SIKG atau rusak.');
        if (! str_starts_with($head, BackupWriter::MAGIC)) {
            throw new BackupFailed('Berkas bukan cadangan SIKG.');
        }
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(substr($head, strlen(BackupWriter::MAGIC)), $this->key);

        while (true) {
            $length = unpack('N', $this->take(4, 'Cadangan terpotong.'));
            $cipher = $this->take((int) ($length[1] ?? 0), 'Cadangan terpotong.');
            $pulled = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
            if ($pulled === false) {
                throw new BackupFailed('Cadangan tidak dapat dibuka: kunci salah atau isi diubah.');
            }
            [$plain, $tag] = $pulled;
            $text = gzuncompress($plain);
            if ($text === false) {
                throw new BackupFailed('Blok cadangan rusak.');
            }
            foreach (explode("\n", $text) as $line) {
                if ($line === '') {
                    continue;
                }
                try {
                    $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new BackupFailed('Catatan cadangan rusak.');
                }
                if (! is_array($record)) {
                    throw new BackupFailed('Catatan cadangan rusak.');
                }
                yield $record;
            }
            if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                $rest = fread($this->stream, 1);
                if ($rest !== '' && $rest !== false) {
                    throw new BackupFailed('Ada sisa data setelah akhir cadangan.');
                }

                return;
            }
        }
    }

    private function take(int $bytes, string $message): string
    {
        $data = '';
        while (strlen($data) < $bytes) {
            $chunk = fread($this->stream, max(1, $bytes - strlen($data)));
            if ($chunk === false || $chunk === '') {
                throw new BackupFailed($message);
            }
            $data .= $chunk;
        }

        return $data;
    }
}
