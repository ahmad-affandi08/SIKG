<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master\Import;

use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Master\ImportableMaster;
use Src\Shared\Application\Master\RepeatableImportKey;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;
use Throwable;

/**
 * MST-09: impor master data dari Excel dengan pratinjau. Pratinjau memeriksa semua baris dengan aturan formulir yang sama,
 * menyimpan baris sah sementara, lalu konfirmasi membuatnya lewat jalur simpan biasa (audit per baris, M10). Baris yang
 * pengenalnya sudah ada dilewati; baris galat tidak ikut. Tidak pernah menimpa atau menghapus data (M5).
 */
final class MasterImporter
{
    public const MAX_ROWS = 1000;

    private const MAX_ERRORS_SHOWN = 200;

    public function __construct(
        private readonly MasterSheet $sheet,
        private readonly MasterRowValidator $validator,
        private readonly MasterImportStore $store,
        private readonly Transaction $tx,
        private readonly AuditLogger $audit,
        private readonly IdGenerator $ids,
    ) {}

    public function template(ImportableMaster $entity): string
    {
        return $this->sheet->template($entity);
    }

    /**
     * @throws MasterImportInvalid
     */
    public function preview(string $actorId, ImportableMaster $entity, string $contents): MasterImportPreview
    {
        $rows = $this->sheet->read($entity, $contents);
        if ($rows === []) {
            throw new MasterImportInvalid('Berkas tidak berisi baris data.');
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new MasterImportInvalid('Maksimal '.self::MAX_ROWS.' baris data per berkas. Pecah berkas menjadi beberapa bagian.');
        }

        $keyField = $entity->importKey();
        $repeatable = $entity instanceof RepeatableImportKey;
        $existing = $repeatable ? [] : $this->existingKeys($entity);
        $seen = [];
        $valid = [];
        $existingRows = [];
        $errors = [];
        $errorCount = 0;

        foreach ($rows as $row) {
            $number = $row['row'];
            $values = MasterValueNormalizer::normalize($entity->fields(), $row['values']);
            $key = $repeatable ? '' : mb_strtolower(trim((string) ($values[$keyField] ?? '')));

            if ($key !== '' && isset($existing[$key])) {
                $existingRows[] = $number;

                continue;
            }
            if ($key !== '' && isset($seen[$key])) {
                $errorCount++;
                $this->addError($errors, $number, ["Sama dengan baris {$seen[$key]} di berkas ini."]);

                continue;
            }

            $result = $this->validator->validate($entity, $values);
            if ($result['errors'] !== []) {
                $errorCount++;
                $this->addError($errors, $number, $result['errors']);

                continue;
            }
            if ($key !== '') {
                $seen[$key] = $number;
            }
            $valid[] = ['row' => $number, 'data' => $result['data']];
        }

        $token = $this->ids->generate();
        $preview = new MasterImportPreview($token, count($rows), count($valid), $existingRows, $errorCount, $errors);
        $this->store->put($token, $actorId, $entity->key(), $valid, $preview->toArray());

        return $preview;
    }

    /**
     * Ringkasan pratinjau yang masih tersimpan (untuk memuat ulang halaman), atau null bila kedaluwarsa.
     *
     * @return array<string, mixed>|null
     */
    public function summary(string $actorId, ImportableMaster $entity, string $token): ?array
    {
        return $this->store->summary($token, $actorId, $entity->key());
    }

    /**
     * @return array{created: int, skipped: int}
     *
     * @throws MasterImportInvalid
     */
    public function commit(string $actorId, ImportableMaster $entity, string $token): array
    {
        $rows = $this->store->get($token, $actorId, $entity->key())
            ?? throw new MasterImportInvalid('Pratinjau impor sudah kedaluwarsa. Unggah ulang berkasnya.');

        // Klaim atomik: dua konfirmasi bersamaan untuk token yang sama tidak boleh sama-sama diterapkan.
        if (! $this->store->claim($token)) {
            throw new MasterImportInvalid('Impor ini sedang atau sudah diproses.');
        }

        try {
            $result = $this->runCommit($actorId, $entity, $rows);
        } catch (Throwable $e) {
            $this->store->release($token);

            throw $e;
        }

        $this->store->forget($token);

        return $result;
    }

    /**
     * @param  list<array{row: int, data: array<string, mixed>}>  $rows
     * @return array{created: int, skipped: int}
     */
    private function runCommit(string $actorId, ImportableMaster $entity, array $rows): array
    {
        return $this->tx->run(function () use ($actorId, $entity, $rows): array {
            $repeatable = $entity instanceof RepeatableImportKey;
            $existing = $repeatable ? [] : $this->existingKeys($entity);
            $created = 0;
            $skipped = 0;
            foreach ($rows as $row) {
                $key = $repeatable ? '' : mb_strtolower(trim((string) ($row['data'][$entity->importKey()] ?? '')));
                if (($key !== '' && isset($existing[$key])) || $this->validator->validate($entity, $row['data'])['errors'] !== []) {
                    $skipped++;

                    continue;
                }
                $entity->create($actorId, $row['data']);
                if ($key !== '') {
                    $existing[$key] = true;
                }
                $created++;
            }

            $this->audit->record(new AuditEntry($actorId, 'master.imported', 'master_import', $this->ids->generate(), null, [
                'entity' => $entity->key(), 'created' => $created, 'skipped' => $skipped,
            ]));

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    /**
     * @return array<string, true>
     */
    private function existingKeys(ImportableMaster $entity): array
    {
        $keys = [];
        foreach ($entity->list() as $row) {
            $value = mb_strtolower(trim((string) ($row[$entity->importKey()] ?? '')));
            if ($value !== '') {
                $keys[$value] = true;
            }
        }

        return $keys;
    }

    /**
     * @param  list<array{row: int, messages: list<string>}>  $errors
     * @param  list<string>  $messages
     */
    private function addError(array &$errors, int $row, array $messages): void
    {
        if (count($errors) < self::MAX_ERRORS_SHOWN) {
            $errors[] = ['row' => $row, 'messages' => $messages];
        }
    }
}
