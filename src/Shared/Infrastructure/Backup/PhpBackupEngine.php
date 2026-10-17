<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Backup;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Src\Shared\Application\Backup\BackupEngine;
use Src\Shared\Application\Backup\BackupFailed;

/**
 * Mesin cadangan PHP murni (M21: tanpa mysqldump, exec, atau proses sistem). Hanya data yang dicadangkan; skema berasal dari
 * migrasi. Tabel volatil (sesi, cache, antrean, denyut, catatan cadangan) tidak ikut. Uji pemulihan memakai tabel
 * TEMPORARY sehingga tidak menyentuh data aplikasi dan tidak memicu commit implisit.
 */
final class PhpBackupEngine implements BackupEngine
{
    private const EXCLUDED = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'system_heartbeats', 'backup_runs'];

    private const NAME_PATTERN = '/^sikg-(\d{8})-(\d{6})\.sikgbak$/';

    private const TEMP_PREFIX = 'zrt_';

    /** Tabel rujukan yang diisi migrasi dengan ID acak per basis data: pemulihan menyamakan ID lewat kunci alaminya, bukan menghapus. */
    private const REFERENCE = ['diagnoses' => ['icd10_code']];

    public function __construct(private readonly string $diskName, private readonly string $sourceDisk, private readonly string $key) {}

    public function backup(DateTimeImmutable $at): array
    {
        $name = 'sikg-'.$at->setTimezone(new DateTimeZone('UTC'))->format('Ymd-His').'.sikgbak';
        $path = tempnam(sys_get_temp_dir(), 'sikgbak');
        $tmp = $path === false ? false : fopen($path, 'w+b');
        if ($path === false || $tmp === false) {
            throw new BackupFailed('Berkas sementara tidak dapat dibuat.');
        }

        try {
            return $this->writeSnapshot($name, $at, $path, $tmp);
        } finally {
            if (is_resource($tmp)) {
                fclose($tmp);
            }
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @param  resource  $tmp
     * @return array{name: string, size: int, tables: int, rows: int, checksum: string}
     */
    private function writeSnapshot(string $name, DateTimeImmutable $at, string $path, $tmp): array
    {
        $writer = new BackupWriter($tmp, $this->key);
        $manifest = [];
        $totalRows = 0;

        DB::transaction(function () use ($writer, $at, &$manifest, &$totalRows): void {
            // Pembacaan pertama membuka snapshot konsisten (REPEATABLE READ) untuk seluruh tabel.
            $migrations = DB::table('migrations')->orderBy('id')->pluck('migration')->all();
            $writer->write(['t' => 'meta', 'version' => 1, 'created_at' => $at->setTimezone(new DateTimeZone('UTC'))->format('c'), 'migrations' => $migrations]);

            foreach ($this->tables() as $table) {
                $columns = Schema::getColumnListing($table);
                $writer->write(['t' => 'table', 'n' => $table, 'columns' => $columns]);
                $hash = hash_init('sha256');
                $rows = 0;
                foreach ($this->orderedRows($table, $columns) as $values) {
                    $writer->write(['t' => 'r', 'n' => $table, 'v' => $values]);
                    hash_update($hash, self::line($values));
                    $rows++;
                }
                $manifest[$table] = ['rows' => $rows, 'sha256' => hash_final($hash)];
                $totalRows += $rows;
            }
            $writer->write(['t' => 'end', 'tables' => $manifest]);
        });
        $writer->close();

        fflush($tmp);
        $size = (int) filesize($path);
        $checksum = hash_file('sha256', $path);
        rewind($tmp);
        Storage::disk($this->diskName)->writeStream($name, $tmp);

        return ['name' => $name, 'size' => $size, 'tables' => count($manifest), 'rows' => $totalRows, 'checksum' => (string) $checksum];
    }

    public function verify(string $name): array
    {
        $created = [];
        $stream = $this->open($name);
        $checksum = $this->checksum($stream);
        rewind($stream);

        try {
            $result = $this->load($stream, self::TEMP_PREFIX, $created, true);
        } finally {
            foreach ($created as $temp) {
                DB::statement(sprintf('DROP TEMPORARY TABLE IF EXISTS %s', self::identifier($temp)));
            }
            fclose($stream);
        }

        return ['name' => $name, 'tables' => $result['tables'], 'rows' => $result['rows'], 'checksum' => $checksum];
    }

    public function restore(string $name): array
    {
        $stream = $this->open($name);
        try {
            $meta = null;
            foreach ((new BackupReader($stream, $this->key))->records() as $record) {
                $meta = $record;
                break;
            }
            if (($meta['t'] ?? null) !== 'meta') {
                throw new BackupFailed('Cadangan tidak memuat penanda versi.');
            }
            // Himpunan migrasi yang sama; urutan dan nomor batch dapat berbeda antara basis data yang dimigrasi bertahap dan yang baru.
            $current = DB::table('migrations')->pluck('migration')->all();
            $wanted = $meta['migrations'];
            sort($current);
            sort($wanted);
            if ($wanted !== $current) {
                throw new BackupFailed('Skema basis data berbeda dari cadangan: jalankan migrasi sampai versi yang sama (php artisan migrate) pada basis data kosong.');
            }
            foreach ($this->tables() as $table) {
                if ($table !== 'migrations' && ! isset(self::REFERENCE[$table]) && DB::table($table)->exists()) {
                    throw new BackupFailed("Basis data tujuan tidak kosong (tabel {$table}). Pemulihan hanya ke basis data baru tanpa data.");
                }
            }
            rewind($stream);

            $created = [];
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                $result = DB::transaction(fn () => $this->load($stream, '', $created, false));
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            return ['name' => $name, 'tables' => $result['tables'], 'rows' => $result['rows']];
        } finally {
            fclose($stream);
        }
    }

    public function mirrorFiles(): array
    {
        $source = Storage::disk($this->sourceDisk);
        $target = Storage::disk($this->diskName);
        $files = 0;
        $copied = 0;
        $missing = 0;

        foreach ($source->allFiles() as $path) {
            $files++;
            $mirror = 'files/'.$path;
            if ($target->exists($mirror) && $target->size($mirror) === $source->size($path)) {
                continue;
            }
            $read = $source->readStream($path);
            if ($read === null || $read === false) {
                $missing++;

                continue;
            }
            $target->writeStream($mirror, $read);
            if (is_resource($read)) {
                fclose($read);
            }
            if ($target->exists($mirror) && $target->size($mirror) === $source->size($path)) {
                $copied++;
            } else {
                $missing++;
            }
        }

        return ['files' => $files, 'copied' => $copied, 'missing' => $missing];
    }

    public function backups(): array
    {
        $result = [];
        foreach (Storage::disk($this->diskName)->files() as $path) {
            if (preg_match(self::NAME_PATTERN, $path, $m) === 1) {
                $at = DateTimeImmutable::createFromFormat('Ymd His', $m[1].' '.$m[2], new DateTimeZone('UTC'));
                if ($at !== false) {
                    $result[$path] = $at;
                }
            }
        }
        asort($result);

        return $result;
    }

    public function remove(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new BackupFailed('Nama berkas cadangan tidak sah.');
        }
        Storage::disk($this->diskName)->delete($name);
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $created  nama tabel sementara yang dibuat (untuk dibersihkan pemanggil)
     * @return array{tables: int, rows: int}
     */
    private function load($stream, string $prefix, array &$created, bool $compare): array
    {
        $reader = new BackupReader($stream, $this->key);
        $streamHash = [];
        $streamRows = [];
        $columns = [];
        $kept = [];
        $pending = [];
        $current = null;
        $manifest = null;
        $tables = 0;
        $rows = 0;
        $sawMeta = false;

        foreach ($reader->records() as $record) {
            switch ($record['t'] ?? null) {
                case 'meta':
                    $sawMeta = true;
                    break;
                case 'table':
                    $this->insertBatch($prefix, $current, $pending);
                    $current = (string) $record['n'];
                    $columns[$current] = $record['columns'];
                    $live = Schema::hasTable($current) ? Schema::getColumnListing($current) : null;
                    if ($live === null) {
                        throw new BackupFailed("Tabel {$current} dalam cadangan tidak ada di basis data (skema berubah sejak cadangan).");
                    }
                    $kept[$current] = array_values(array_intersect($columns[$current], $live));
                    if ($prefix !== '') {
                        DB::statement(sprintf('CREATE TEMPORARY TABLE %s LIKE %s', self::identifier($prefix.$current), self::identifier($current)));
                        $created[] = $prefix.$current;
                    }
                    $streamHash[$current] = hash_init('sha256');
                    $streamRows[$current] = 0;
                    $tables++;
                    break;
                case 'r':
                    $table = (string) $record['n'];
                    if (! isset($streamHash[$table])) {
                        throw new BackupFailed('Urutan catatan cadangan tidak sah.');
                    }
                    hash_update($streamHash[$table], self::line($record['v']));
                    $streamRows[$table]++;
                    $rows++;
                    $assoc = [];
                    foreach ($columns[$table] as $i => $column) {
                        if (in_array($column, $kept[$table], true)) {
                            $assoc[$column] = $record['v'][$i];
                        }
                    }
                    if ($table === 'migrations' && $prefix === '') {
                        break;
                    }
                    $current = $table;
                    $pending[] = $assoc;
                    if (count($pending) >= max(1, min(500, intdiv(60000, max(1, count($assoc)))))) {
                        $this->insertBatch($prefix, $current, $pending);
                    }
                    break;
                case 'end':
                    $manifest = $record['tables'];
                    break;
            }
        }
        $this->insertBatch($prefix, $current, $pending);

        if (! $sawMeta || ! is_array($manifest)) {
            throw new BackupFailed('Cadangan tidak lengkap (tanpa penanda atau manifest).');
        }

        $problems = [];
        foreach ($manifest as $table => $info) {
            if (! isset($streamHash[$table]) || $streamRows[$table] !== $info['rows'] || hash_final(hash_copy($streamHash[$table])) !== $info['sha256']) {
                $problems[] = $table.' (isi cadangan tidak cocok dengan manifest)';
            }
        }
        if ($compare) {
            foreach ($kept as $table => $cols) {
                if ($cols !== $columns[$table]) {
                    $problems[] = $table.' (kolom berubah sejak cadangan)';

                    continue;
                }
                $hash = hash_init('sha256');
                $count = 0;
                foreach ($this->orderedRows($prefix.$table, $cols, $table) as $values) {
                    hash_update($hash, self::line($values));
                    $count++;
                }
                $info = $manifest[$table] ?? null;
                if ($info === null || $count !== $info['rows'] || hash_final($hash) !== $info['sha256']) {
                    $problems[] = $table.' (hasil pemulihan berbeda dari cadangan)';
                }
            }
        }
        if ($problems !== []) {
            throw new BackupFailed('Uji pemulihan gagal pada tabel: '.implode(', ', $problems).'.');
        }

        return ['tables' => $tables, 'rows' => $rows];
    }

    /**
     * @param  list<array<string, mixed>>  $pending
     */
    private function insertBatch(string $prefix, ?string $table, array &$pending): void
    {
        if ($pending === [] || $table === null) {
            return;
        }
        if ($prefix === '' && isset(self::REFERENCE[$table])) {
            DB::table($table)->upsert($pending, self::REFERENCE[$table], array_keys($pending[0]));
        } else {
            DB::table($prefix.$table)->insert($pending);
        }
        $pending = [];
    }

    /**
     * @param  list<string>  $columns
     * @return iterable<list<mixed>>
     */
    private function orderedRows(string $table, array $columns, ?string $keySource = null): iterable
    {
        $order = $this->primaryKey($keySource ?? $table, $columns);
        $query = DB::table($table)->select($columns);
        foreach ($order as $column) {
            $query->orderBy($column);
        }
        foreach ($query->lazy(1000) as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $row->{$column};
            }
            yield $values;
        }
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function primaryKey(string $table, array $columns): array
    {
        $keys = DB::select(sprintf('SHOW KEYS FROM %s WHERE Key_name = ?', self::identifier($table)), ['PRIMARY']);
        usort($keys, fn ($a, $b) => $a->Seq_in_index <=> $b->Seq_in_index);
        $order = array_values(array_map(fn ($k) => (string) $k->Column_name, $keys));

        return $order === [] ? $columns : $order;
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        $names = [];
        foreach (Schema::getTables() as $table) {
            $name = (string) $table['name'];
            if (! in_array($name, self::EXCLUDED, true) && ! str_starts_with($name, self::TEMP_PREFIX)) {
                $names[] = $name;
            }
        }
        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * Nama tabel tidak dapat di-binding; hanya nama yang berasal dari skema (huruf, angka, garis bawah) yang diterima, lalu dikutip.
     */
    private static function identifier(string $name): string
    {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) !== 1) {
            throw new BackupFailed('Nama tabel tidak sah dalam cadangan.');
        }

        return '`'.$name.'`';
    }

    /**
     * @param  list<mixed>  $values
     */
    private static function line(array $values): string
    {
        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @return resource
     */
    private function open(string $name)
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new BackupFailed('Nama berkas cadangan tidak sah.');
        }
        $disk = Storage::disk($this->diskName);
        if (! $disk->exists($name)) {
            throw new BackupFailed("Berkas cadangan {$name} tidak ditemukan.");
        }
        $remote = $disk->readStream($name);
        $local = tmpfile();
        if ($remote === null || $remote === false || $local === false) {
            throw new BackupFailed('Berkas cadangan tidak dapat dibaca.');
        }
        stream_copy_to_stream($remote, $local);
        if (is_resource($remote)) {
            fclose($remote);
        }
        rewind($local);

        return $local;
    }

    /**
     * @param  resource  $stream
     */
    private function checksum($stream): string
    {
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);

        return hash_final($hash);
    }
}
