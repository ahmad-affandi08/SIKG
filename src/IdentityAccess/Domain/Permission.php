<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

/**
 * Izin granular per aksi (docs/PRD/00 bagian 4 dan docs/PRD/08). Peran hanyalah kelompok izin.
 */
enum Permission: string
{
    case PatientView = 'patient.view';
    case PatientCreate = 'patient.create';
    case PatientUpdate = 'patient.update';
    case PatientDelete = 'patient.delete';
    case PatientImport = 'patient.import';
    case PatientMerge = 'patient.merge';

    case AppointmentView = 'appointment.view';
    case AppointmentManage = 'appointment.manage';

    case EncounterView = 'encounter.view';
    case EncounterCreate = 'encounter.create';
    case EncounterWrite = 'encounter.write';
    case EncounterClose = 'encounter.close';
    case EncounterAddendum = 'encounter.addendum';
    case EncounterCorrect = 'encounter.correct';
    case TreatmentPlanManage = 'treatment_plan.manage';

    case OdontogramView = 'odontogram.view';
    case OdontogramWrite = 'odontogram.write';

    case PrescriptionView = 'prescription.view';
    case PrescriptionCreate = 'prescription.create';
    case PrescriptionUpdate = 'prescription.update';

    case InvoiceView = 'invoice.view';
    case InvoiceEdit = 'invoice.edit';
    case InvoiceDiscount = 'invoice.discount';
    case InvoiceVoid = 'invoice.void';

    case PaymentView = 'payment.view';
    case PaymentCreate = 'payment.create';
    case PaymentRefund = 'payment.refund';
    case CashClose = 'cash.close';

    case StockView = 'stock.view';
    case StockManage = 'stock.manage';
    case StockOverride = 'stock.override';

    case TariffView = 'tariff.view';
    case TariffManage = 'tariff.manage';

    case ReportView = 'report.view';
    case ReportFinancial = 'report.financial';
    case ReportFinancialOwn = 'report.financial.own';
    case ReportClinical = 'report.clinical';
    case ReportClinicalOwn = 'report.clinical.own';

    case RecordExport = 'record.export';
    case RecordPrint = 'record.print';

    case UserView = 'user.view';
    case UserManage = 'user.manage';
    case RoleManage = 'role.manage';
    case AuditView = 'audit.view';
    case SettingManage = 'setting.manage';

    /**
     * Kelompok modul untuk tampilan halaman peran dan izin (AUTH-07).
     */
    public function module(): string
    {
        return match ($this) {
            self::PatientView, self::PatientCreate, self::PatientUpdate, self::PatientDelete, self::PatientImport, self::PatientMerge => 'Pasien',
            self::AppointmentView, self::AppointmentManage => 'Jadwal dan antrean',
            self::EncounterView, self::EncounterCreate, self::EncounterWrite, self::EncounterClose, self::EncounterAddendum,
            self::EncounterCorrect, self::TreatmentPlanManage, self::OdontogramView, self::OdontogramWrite => 'Rekam medis dan odontogram',
            self::PrescriptionView, self::PrescriptionCreate, self::PrescriptionUpdate, self::StockView, self::StockManage, self::StockOverride => 'Obat dan stok',
            self::InvoiceView, self::InvoiceEdit, self::InvoiceDiscount, self::InvoiceVoid,
            self::PaymentView, self::PaymentCreate, self::PaymentRefund, self::CashClose, self::TariffView, self::TariffManage => 'Kasir dan tarif',
            self::ReportView, self::ReportFinancial, self::ReportFinancialOwn, self::ReportClinical, self::ReportClinicalOwn => 'Laporan',
            self::RecordExport, self::RecordPrint => 'Ekspor dan cetak rekam medis',
            self::UserView, self::UserManage, self::RoleManage, self::AuditView, self::SettingManage => 'Pengguna dan sistem',
        };
    }

    /**
     * Penjelasan singkat bahasa Indonesia untuk halaman peran dan izin.
     */
    public function label(): string
    {
        return match ($this) {
            self::PatientView => 'Melihat data pasien',
            self::PatientCreate => 'Mendaftarkan pasien baru',
            self::PatientUpdate => 'Mengubah data pasien',
            self::PatientDelete => 'Mengarsipkan pasien',
            self::PatientImport => 'Mengimpor data pasien',
            self::PatientMerge => 'Menggabungkan data pasien ganda',
            self::AppointmentView => 'Melihat jadwal dan antrean',
            self::AppointmentManage => 'Membuat dan mengubah janji, check-in',
            self::EncounterView => 'Membuka rekam medis',
            self::EncounterCreate => 'Memulai kunjungan',
            self::EncounterWrite => 'Mengisi catatan, diagnosa, dan tindakan',
            self::EncounterClose => 'Menutup kunjungan dan menerbitkan surat',
            self::EncounterAddendum => 'Menambah addendum pada rekam medis terkunci',
            self::EncounterCorrect => 'Memperbaiki rekam medis dalam 2x24 jam',
            self::TreatmentPlanManage => 'Mengelola rencana perawatan',
            self::OdontogramView => 'Melihat odontogram',
            self::OdontogramWrite => 'Mengubah odontogram',
            self::PrescriptionView => 'Melihat resep',
            self::PrescriptionCreate => 'Membuat resep',
            self::PrescriptionUpdate => 'Mengubah resep',
            self::StockView => 'Melihat stok obat',
            self::StockManage => 'Mengelola stok masuk dan opname',
            self::StockOverride => 'Menimpa peringatan stok kurang',
            self::InvoiceView => 'Melihat tagihan',
            self::InvoiceEdit => 'Menyunting tagihan',
            self::InvoiceDiscount => 'Memberi diskon tagihan',
            self::InvoiceVoid => 'Membatalkan tagihan',
            self::PaymentView => 'Melihat pembayaran',
            self::PaymentCreate => 'Menerima pembayaran',
            self::PaymentRefund => 'Melakukan refund',
            self::CashClose => 'Menutup kas harian',
            self::TariffView => 'Melihat tarif tindakan',
            self::TariffManage => 'Mengubah tarif tindakan',
            self::ReportView => 'Membuka halaman laporan',
            self::ReportFinancial => 'Laporan keuangan (seluruh klinik)',
            self::ReportFinancialOwn => 'Laporan keuangan (kas sendiri saja)',
            self::ReportClinical => 'Laporan klinis (seluruh klinik)',
            self::ReportClinicalOwn => 'Laporan klinis (pasien sendiri saja)',
            self::RecordExport => 'Mengekspor rekam medis pasien',
            self::RecordPrint => 'Mencetak rekam medis dan dokumen klinis',
            self::UserView => 'Melihat daftar pengguna',
            self::UserManage => 'Mengelola pengguna dan reset kata sandi',
            self::RoleManage => 'Mengatur izin per peran',
            self::AuditView => 'Melihat audit log dan jejak akses',
            self::SettingManage => 'Mengelola pengaturan dan master data',
        };
    }

    /**
     * Izin yang tidak boleh hilang dari peran Pemilik agar klinik tidak terkunci dari pengelolaan pengguna dan peran.
     *
     * @return list<self>
     */
    public static function ownerProtected(): array
    {
        return [self::UserView, self::UserManage, self::RoleManage];
    }
}
