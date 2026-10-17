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
}
