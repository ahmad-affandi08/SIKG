<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

/**
 * Matriks hak akses bawaan (docs/PRD/00-ringkasan-lingkup-persona.md bagian 4), satu sumber kebenaran di kode.
 * Bawaan aman: izin yang tidak tercantum berarti ditolak.
 */
final class DefaultRolePermissions
{
    /**
     * @return list<Permission>
     */
    public static function for(Role $role): array
    {
        return match ($role) {
            Role::Pemilik => [
                Permission::PatientView,
                Permission::AppointmentView,
                Permission::EncounterView,
                Permission::OdontogramView,
                Permission::PrescriptionView,
                Permission::InvoiceView, Permission::InvoiceVoid,
                Permission::PaymentView, Permission::PaymentRefund,
                Permission::StockView,
                Permission::TariffView, Permission::TariffManage,
                Permission::ReportView, Permission::ReportFinancial, Permission::ReportClinical,
                Permission::UserView, Permission::UserManage, Permission::RoleManage,
                Permission::AuditView,
                Permission::SettingManage,
            ],
            Role::Admin => [
                Permission::PatientView, Permission::PatientCreate, Permission::PatientUpdate, Permission::PatientDelete,
                Permission::PatientImport, Permission::PatientMerge,
                Permission::AppointmentView, Permission::AppointmentManage,
                Permission::EncounterView,
                Permission::OdontogramView,
                Permission::PrescriptionView,
                Permission::InvoiceView, Permission::InvoiceVoid,
                Permission::PaymentView, Permission::PaymentRefund,
                Permission::StockView, Permission::StockManage, Permission::StockOverride,
                Permission::TariffView, Permission::TariffManage,
                Permission::ReportView, Permission::ReportFinancial, Permission::ReportClinical,
                Permission::RecordExport, Permission::RecordPrint,
                Permission::UserView, Permission::UserManage, Permission::RoleManage,
                Permission::AuditView,
                Permission::SettingManage,
            ],
            Role::Resepsionis => [
                Permission::PatientView, Permission::PatientCreate, Permission::PatientUpdate,
                Permission::AppointmentView, Permission::AppointmentManage,
                Permission::InvoiceView,
                Permission::TariffView,
            ],
            Role::Dokter => [
                Permission::PatientView,
                Permission::AppointmentView,
                Permission::EncounterView, Permission::EncounterCreate, Permission::EncounterWrite,
                Permission::EncounterClose, Permission::EncounterAddendum,
                Permission::OdontogramView, Permission::OdontogramWrite,
                Permission::PrescriptionView, Permission::PrescriptionCreate, Permission::PrescriptionUpdate,
                Permission::InvoiceView,
                Permission::StockView,
                Permission::TariffView,
                Permission::ReportView, Permission::ReportClinicalOwn,
            ],
            Role::Perawat => [
                Permission::PatientView,
                Permission::AppointmentView,
                Permission::EncounterView, Permission::EncounterCreate, Permission::EncounterWrite,
                Permission::OdontogramView,
                Permission::PrescriptionView,
                Permission::StockView, Permission::StockManage,
                Permission::TariffView,
            ],
            Role::Kasir => [
                Permission::PatientView,
                Permission::AppointmentView,
                Permission::PrescriptionView,
                Permission::InvoiceView, Permission::InvoiceEdit, Permission::InvoiceDiscount,
                Permission::PaymentView, Permission::PaymentCreate,
                Permission::CashClose,
                Permission::TariffView,
                Permission::ReportView, Permission::ReportFinancialOwn,
            ],
        };
    }
}
