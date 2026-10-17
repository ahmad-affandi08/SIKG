<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure\Import;

use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Src\Patient\Application\Import\ImportFileInvalid;
use Src\Patient\Application\Import\SpreadsheetReader;

/**
 * Pembaca Excel berpotongan memakai mesin PhpSpreadsheet milik maatwebsite/excel: hanya baris pada potongan yang
 * dimuat (filter baca), sehingga memori tidak bergantung pada ukuran berkas.
 */
final class XlsxSpreadsheetReader implements SpreadsheetReader
{
    /** @var list<string> */
    public const HEADERS = ['nama', 'tanggal_lahir', 'jenis_kelamin', 'nik', 'hp', 'alamat', 'pekerjaan', 'alergi', 'penyakit_sistemik', 'wali_nama', 'wali_hubungan', 'wali_hp'];

    private const REQUIRED = ['nama', 'tanggal_lahir', 'jenis_kelamin'];

    public function countRows(string $localPath): int
    {
        try {
            $reader = IOFactory::createReaderForFile($localPath);
            $info = $reader->listWorksheetInfo($localPath);
            $headers = $this->headers($localPath);
        } catch (SpreadsheetException) {
            throw new ImportFileInvalid('Berkas tidak dapat dibaca. Gunakan templat .xlsx dari sistem.');
        }

        foreach (self::REQUIRED as $required) {
            if (! in_array($required, $headers, true)) {
                throw new ImportFileInvalid("Kolom wajib \"{$required}\" tidak ditemukan pada baris judul.");
            }
        }

        return max(0, (int) ($info[0]['totalRows'] ?? 1) - 1);
    }

    public function read(string $localPath, int $offset, int $limit): array
    {
        $headers = $this->headers($localPath);
        $first = $offset + 2;
        $last = $first + $limit - 1;

        $reader = IOFactory::createReaderForFile($localPath);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class($first, $last) implements IReadFilter
        {
            public function __construct(private readonly int $first, private readonly int $last) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row >= $this->first && $row <= $this->last;
            }
        });
        $sheet = $reader->load($localPath)->getActiveSheet();

        $rows = [];
        $highest = min($last, $sheet->getHighestRow());
        for ($r = $first; $r <= $highest; $r++) {
            $values = $sheet->rangeToArray('A'.$r.':'.$sheet->getHighestColumn().$r, null, true, false)[0] ?? [];
            $row = [];
            foreach ($headers as $i => $name) {
                $value = $values[$i] ?? null;
                $row[$name] = is_scalar($value) || $value === null ? $value : (string) json_encode($value);
            }
            $rows[$r] = $row;
        }

        return $rows;
    }

    public function template(): string
    {
        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('Pasien');
        $active->fromArray([
            self::HEADERS,
            ['Contoh Pasien', '1990-01-31', 'L', '9999010131900001', '081200000000', 'Jl. Contoh No. 1', 'Karyawan', 'Penisilin', 'Diabetes', '', '', ''],
        ]);
        $active->getComment('A1')->getText()->createTextRun('Hapus baris contoh sebelum mengisi. Tanggal lahir: YYYY-MM-DD atau DD/MM/YYYY. Jenis kelamin: L atau P. Alergi dan penyakit sistemik dipisah titik koma.');

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new ImportFileInvalid('Templat tidak dapat dibuat.');
        }
        (new Xlsx($sheet))->save($stream);
        rewind($stream);
        $contents = (string) stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    /**
     * @return list<string>
     */
    private function headers(string $localPath): array
    {
        $reader = IOFactory::createReaderForFile($localPath);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row === 1;
            }
        });
        $sheet = $reader->load($localPath)->getActiveSheet();
        $cells = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1', null, true, false)[0] ?? [];

        return array_values(array_map(
            fn ($cell) => trim((string) preg_replace('/\s+/', '_', mb_strtolower(trim(is_scalar($cell) ? (string) $cell : '')))),
            $cells,
        ));
    }
}
