<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Master\Import;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Src\Shared\Application\Master\Import\MasterImporter;
use Src\Shared\Application\Master\Import\MasterImportInvalid;
use Src\Shared\Application\Master\Import\MasterSheet;
use Src\Shared\Application\Master\ImportableMaster;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Application\Master\RepeatableImportKey;
use Src\Shared\Domain\IdGenerator;

/**
 * Templat dan pembaca Excel untuk impor master data memakai PhpSpreadsheet (mesin yang sama dengan impor pasien dan ekspor
 * laporan). Berkas sementara hanya ada selama pembacaan di disk privat lalu dihapus; tanpa proses sistem (M21).
 */
final readonly class PhpSpreadsheetMasterSheet implements MasterSheet
{
    private const MAX_BYTES = 2_097_152;

    public function __construct(private IdGenerator $ids) {}

    public function template(ImportableMaster $entity): string
    {
        $book = new Spreadsheet;
        $data = $book->getActiveSheet();
        $data->setTitle('Data');
        $fields = $entity->fields();

        foreach ($fields as $i => $field) {
            $column = $this->column($i);
            $data->setCellValue($column.'1', $this->header($field));
            $data->getColumnDimension($column)->setWidth(max(16, min(40, mb_strlen($this->header($field)) + 6)));
            if ($field->type === 'select' && $field->options !== []) {
                $list = implode(',', array_column($field->options, 'label'));
                if (mb_strlen($list) <= 250) {
                    $validation = $data->getCell($column.'2')->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                        ->setFormula1('"'.$list.'"');
                    for ($r = 3; $r <= 200; $r++) {
                        $data->getCell($column.$r)->setDataValidation(clone $validation);
                    }
                }
            }
        }
        $last = $this->column(count($fields) - 1);
        $data->getStyle("A1:{$last}1")->getFont()->setBold(true);
        $data->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F1');
        $data->freezePane('A2');

        $help = $book->createSheet();
        $help->setTitle('Petunjuk');
        $help->fromArray([['Kolom', 'Wajib', 'Isi', 'Pilihan']], null, 'A1');
        $row = 2;
        foreach ($fields as $field) {
            $help->fromArray([[
                $this->header($field), $field->required ? 'Ya' : 'Tidak', $this->hint($field),
                $field->type === 'select' ? implode('; ', array_column($field->options, 'label')) : '',
            ]], null, 'A'.$row);
            $row++;
        }
        $help->setCellValue('A'.($row + 1), 'Isi lembar "Data" mulai baris 2. Maksimal '.MasterImporter::MAX_ROWS.' baris per berkas. '.(
            $entity instanceof RepeatableImportKey
                ? $entity->importWarning()
                : 'Baris yang '.$this->header($this->keyField($entity)).'-nya sudah ada di sistem dilewati, tidak ditimpa.'
        ));
        $help->getStyle('A1:D1')->getFont()->setBold(true);
        foreach (['A' => 28, 'B' => 8, 'C' => 60, 'D' => 60] as $col => $width) {
            $help->getColumnDimension($col)->setWidth($width);
        }
        $book->setActiveSheetIndex(0);

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new MasterImportInvalid('Templat tidak dapat dibuat.');
        }
        (new Xlsx($book))->save($stream);
        rewind($stream);
        $contents = (string) stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    public function read(ImportableMaster $entity, string $contents): array
    {
        if ($contents === '' || strlen($contents) > self::MAX_BYTES) {
            throw new MasterImportInvalid('Berkas kosong atau lebih dari 2 MB.');
        }
        $extension = str_starts_with($contents, 'PK') ? 'xlsx' : 'csv';
        $disk = Storage::disk('private');
        $tmp = "imports/tmp-{$this->ids->generate()}.{$extension}";
        $disk->put($tmp, $contents);

        try {
            return $this->parse($entity, $disk->path($tmp));
        } catch (SpreadsheetException) {
            throw new MasterImportInvalid('Berkas tidak dapat dibaca. Gunakan templat .xlsx dari sistem atau berkas .csv.');
        } finally {
            $disk->delete($tmp);
        }
    }

    /**
     * @return list<array{row: int, values: array<string, string>}>
     */
    private function parse(ImportableMaster $entity, string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        // Hanya baris judul dan MAX_ROWS + 1 baris pertama yang dimuat, sehingga berkas raksasa tidak menghabiskan memori;
        // baris ekstra tetap terdeteksi dan ditolak di bawah.
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= MasterImporter::MAX_ROWS + 2;
            }
        });
        if ($reader instanceof Csv) {
            // Excel berbahasa Indonesia mengekspor CSV dengan titik koma.
            $firstLine = (string) strtok((string) file_get_contents($path), "\n");
            $reader->setDelimiter(substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',');
        }
        $sheet = $reader->load($path)->getSheet(0);

        if ($sheet->getHighestDataRow() > MasterImporter::MAX_ROWS + 1) {
            throw new MasterImportInvalid('Maksimal '.MasterImporter::MAX_ROWS.' baris data per berkas. Pecah berkas menjadi beberapa bagian.');
        }

        $map = $this->columnMap($entity, $sheet);
        foreach ($entity->fields() as $field) {
            if ($field->required && ! isset($map[$field->key])) {
                throw new MasterImportInvalid('Kolom wajib "'.$this->header($field).'" tidak ditemukan pada baris judul.');
            }
        }

        $types = [];
        foreach ($entity->fields() as $field) {
            $types[$field->key] = $field->type;
        }

        $rows = [];
        $highest = $sheet->getHighestDataRow();
        for ($r = 2; $r <= $highest; $r++) {
            $values = [];
            $filled = false;
            foreach ($map as $key => $column) {
                $cell = $sheet->getCell($column.$r)->getValue();
                $text = $this->text($cell, $types[$key] ?? 'text');
                $values[$key] = $text;
                $filled = $filled || $text !== '';
            }
            if ($filled) {
                $rows[] = ['row' => $r, 'values' => $values];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, string> kunci kolom entitas => huruf kolom lembar
     */
    private function columnMap(ImportableMaster $entity, Worksheet $sheet): array
    {
        $byName = [];
        foreach ($entity->fields() as $field) {
            $byName[$this->normalize($field->key)] = $field->key;
            $byName[$this->normalize($this->header($field))] = $field->key;
            $byName[$this->normalize($field->label)] = $field->key;
        }

        $map = [];
        $highest = $sheet->getHighestDataColumn(1);
        foreach ($sheet->rangeToArray('A1:'.$highest.'1', null, true, false)[0] ?? [] as $i => $title) {
            $name = $this->normalize(is_scalar($title) ? (string) $title : '');
            if ($name !== '' && isset($byName[$name]) && ! isset($map[$byName[$name]])) {
                $map[$byName[$name]] = $this->column((int) $i);
            }
        }

        return $map;
    }

    private function text(mixed $cell, string $type): string
    {
        if ($cell === null) {
            return '';
        }
        if ($type === 'date' && (is_int($cell) || is_float($cell))) {
            return Date::excelToDateTimeObject($cell)->format('Y-m-d');
        }
        if (is_bool($cell)) {
            return $cell ? '1' : '0';
        }

        return is_scalar($cell) ? trim((string) $cell) : '';
    }

    private function header(MasterField $field): string
    {
        return trim((string) preg_replace('/\s*\(Rp\)$/', '', $field->label));
    }

    private function hint(MasterField $field): string
    {
        $text = match ($field->type) {
            'money' => 'Rupiah bulat, contoh 250000 atau Rp 250.000.',
            'number' => 'Bilangan bulat.',
            'date' => 'Tanggal YYYY-MM-DD atau DD/MM/YYYY.',
            'select' => 'Pilih salah satu dari kolom Pilihan.',
            default => $field->max !== null ? "Teks, maksimal {$field->max} karakter." : 'Teks.',
        };

        return $field->help !== null ? $text.' '.$field->help : $text;
    }

    private function keyField(ImportableMaster $entity): MasterField
    {
        foreach ($entity->fields() as $field) {
            if ($field->key === $entity->importKey()) {
                return $field;
            }
        }

        return $entity->fields()[0];
    }

    private function normalize(string $text): string
    {
        return mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', preg_replace('/\s*\(Rp\)$/i', '', $text) ?? $text));
    }

    private function column(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26).$name;
        }

        return $name;
    }
}
