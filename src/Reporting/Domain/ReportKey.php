<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

/**
 * Sepuluh laporan PRD bagian 15 (LAP-01 sampai LAP-10). Nilai enum adalah kunci pada URL `/reports/{key}`.
 */
enum ReportKey: string
{
    case DailySummary = 'daily-summary';
    case Revenue = 'revenue';
    case DoctorFees = 'doctor-fees';
    case Visits = 'visits';
    case Procedures = 'procedures';
    case Receivables = 'receivables';
    case Stock = 'stock';
    case CashClosing = 'cash-closing';
    case RecordCompleteness = 'record-completeness';
    case UserActivity = 'user-activity';

    public function code(): string
    {
        return match ($this) {
            self::DailySummary => 'LAP-01',
            self::Revenue => 'LAP-02',
            self::DoctorFees => 'LAP-03',
            self::Visits => 'LAP-04',
            self::Procedures => 'LAP-05',
            self::Receivables => 'LAP-06',
            self::Stock => 'LAP-07',
            self::CashClosing => 'LAP-08',
            self::RecordCompleteness => 'LAP-09',
            self::UserActivity => 'LAP-10',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::DailySummary => 'Ringkasan harian',
            self::Revenue => 'Pendapatan',
            self::DoctorFees => 'Jasa dokter',
            self::Visits => 'Kunjungan',
            self::Procedures => 'Tindakan',
            self::Receivables => 'Piutang dan uang muka',
            self::Stock => 'Stok obat dan bahan',
            self::CashClosing => 'Tutup kas',
            self::RecordCompleteness => 'Kelengkapan rekam medis',
            self::UserActivity => 'Aktivitas pengguna',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DailySummary => 'Penerimaan per metode bayar, jumlah kunjungan, pasien baru, tindakan terbanyak, dan piutang baru.',
            self::Revenue => 'Pendapatan harian atau bulanan, per dokter, per kategori tindakan, dan penerimaan per metode bayar.',
            self::DoctorFees => 'Rekap jasa dokter per periode berdasarkan skema jasa di master dokter.',
            self::Visits => 'Kunjungan per hari, pasien baru dan lama, janji tidak datang, dan waktu tunggu.',
            self::Procedures => 'Frekuensi dan nilai per jenis tindakan.',
            self::Receivables => 'Tagihan belum lunas, umur piutang, dan uang muka yang belum terpakai.',
            self::Stock => 'Stok saat ini, di bawah minimum, hampir kedaluwarsa, dan pergerakan stok.',
            self::CashClosing => 'Per kasir per hari: jumlah menurut sistem, uang fisik, dan selisih.',
            self::RecordCompleteness => 'Kunjungan tanpa diagnosa, tindakan, catatan, atau belum ditutup dokter.',
            self::UserActivity => 'Ringkasan audit log per pengguna dan periode.',
        };
    }

    /** Kelompok menu di halaman daftar laporan. */
    public function group(): string
    {
        return match ($this) {
            self::DailySummary, self::Revenue, self::DoctorFees, self::Receivables, self::CashClosing => 'Keuangan',
            self::Visits, self::Procedures, self::RecordCompleteness => 'Klinis',
            self::Stock => 'Stok',
            self::UserActivity => 'Audit',
        };
    }

    /**
     * Izin yang membuka laporan secara penuh (seluruh klinik). Cukup salah satu.
     *
     * @return list<string>
     */
    public function fullPermissions(): array
    {
        return match ($this) {
            self::DailySummary, self::Revenue, self::DoctorFees, self::Receivables, self::CashClosing => ['report.financial'],
            self::Visits, self::Procedures, self::RecordCompleteness => ['report.clinical'],
            self::Stock => ['report.clinical', 'stock.manage'],
            self::UserActivity => ['audit.view'],
        };
    }

    /**
     * Izin "sendiri": Kasir untuk kas yang ia terima, Dokter untuk pasien yang ia tangani (matriks PRD 00 bagian 4).
     *
     * @return list<string>
     */
    public function ownPermissions(): array
    {
        return match ($this) {
            self::DailySummary, self::Receivables, self::CashClosing => ['report.financial.own'],
            self::Procedures, self::RecordCompleteness => ['report.clinical.own'],
            default => [],
        };
    }

    public function usesDoctorFilter(): bool
    {
        return in_array($this, [self::DailySummary, self::Revenue, self::DoctorFees, self::Visits, self::Procedures, self::Receivables, self::RecordCompleteness], true);
    }

    public function usesMethodFilter(): bool
    {
        return in_array($this, [self::DailySummary, self::Revenue, self::Receivables], true);
    }

    /** Laporan dengan pilihan penyajian harian atau bulanan. */
    public function usesGrain(): bool
    {
        return $this === self::Revenue || $this === self::Visits;
    }

    /** Laporan yang secara bawaan menampilkan satu hari (hari ini); lainnya sebulan berjalan. */
    public function singleDayDefault(): bool
    {
        return $this === self::DailySummary;
    }
}
