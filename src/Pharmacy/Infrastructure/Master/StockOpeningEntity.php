<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Master;

use LogicException;
use Src\Pharmacy\Application\StockService;
use Src\Pharmacy\Infrastructure\Eloquent\MedicineModel;
use Src\Shared\Application\Master\ImportableMaster;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Application\Master\RepeatableImportKey;

/**
 * Impor stok awal obat dan bahan dari Excel (MST-11): satu baris menjadi satu penerimaan stok (mutasi masuk bertanda "Stok awal")
 * lewat jalur penerimaan biasa, sehingga saldo, batch, kedaluwarsa, dan audit sama dengan penerimaan manual. Obat dicocokkan lewat
 * nama di katalog; obat yang belum ada atau masih nonaktif ditolak dengan petunjuk. Hanya-impor: tidak punya halaman daftar.
 * Satu obat boleh punya beberapa baris (beberapa batch).
 */
final class StockOpeningEntity implements ImportableMaster, RepeatableImportKey
{
    public function __construct(private readonly StockService $stock) {}

    public function key(): string
    {
        return 'stock-opening';
    }

    public function title(): string
    {
        return 'Stok awal obat dan bahan';
    }

    public function viewPermission(): string
    {
        return 'stock.view';
    }

    public function managePermission(): string
    {
        return 'stock.manage';
    }

    public function importKey(): string
    {
        return 'medicine_name';
    }

    public function importHome(): string
    {
        return '/stock';
    }

    public function importWarning(): string
    {
        return 'Impor ini MENAMBAH stok setiap kali dikonfirmasi: jangan mengunggah berkas yang sama dua kali. Obat harus sudah ada di katalog dan aktif; satu obat boleh punya beberapa baris untuk beberapa batch.';
    }

    public function fields(): array
    {
        return [
            new MasterField('medicine_name', 'Nama obat atau bahan', 'text', required: true, max: 255, help: 'Sama persis dengan nama di katalog.'),
            new MasterField('quantity', 'Jumlah', 'number', required: true, help: 'Bilangan bulat, dalam satuan katalog.'),
            new MasterField('batch_no', 'Nomor batch', 'text', max: 50),
            new MasterField('expiry_date', 'Tanggal kedaluwarsa', 'date', help: 'Kosong bila tidak ada tanggal kedaluwarsa.'),
        ];
    }

    public function columns(): array
    {
        return ['medicine_name', 'quantity', 'batch_no', 'expiry_date'];
    }

    public function rules(?string $ignoreId): array
    {
        return [
            'medicine_name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, callable $fail): void {
                $problem = $this->problem((string) $value);
                if ($problem !== null) {
                    $fail($problem);
                }
            }],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'batch_no' => ['nullable', 'string', 'max:50'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function list(): array
    {
        return [];
    }

    public function create(string $actorId, array $data): string
    {
        $medicine = $this->active((string) $data['medicine_name']);
        if ($medicine === null) {
            throw new LogicException('Obat tidak ditemukan saat impor stok awal.');
        }
        $this->stock->receive(
            $actorId,
            $medicine->id,
            (int) $data['quantity'],
            isset($data['batch_no']) ? (string) $data['batch_no'] : null,
            isset($data['expiry_date']) ? (string) $data['expiry_date'] : null,
            'Stok awal (impor Excel)',
        );

        return $medicine->id;
    }

    public function update(string $actorId, string $id, array $data): void
    {
        throw new LogicException('Stok awal tidak dapat diubah lewat impor.');
    }

    public function setActive(string $actorId, string $id, bool $active): void
    {
        throw new LogicException('Stok awal tidak memiliki status aktif.');
    }

    private function active(string $name): ?MedicineModel
    {
        return MedicineModel::query()->where('name', $name)->where('is_active', true)->first();
    }

    private function problem(string $name): ?string
    {
        $matches = MedicineModel::query()->where('name', $name)->get();
        if ($matches->isEmpty()) {
            return 'Obat tidak ada di katalog. Tambahkan lebih dulu lewat Katalog obat dan bahan atau impor katalog.';
        }
        $active = $matches->where('is_active', true);
        if ($active->count() > 1) {
            return 'Ada lebih dari satu obat aktif dengan nama ini; ubah salah satunya agar namanya berbeda.';
        }
        if ($active->isEmpty()) {
            return 'Obat masih nonaktif. Isi harga jual lalu aktifkan di Katalog obat dan bahan sebelum mengisi stok.';
        }

        return null;
    }
}
