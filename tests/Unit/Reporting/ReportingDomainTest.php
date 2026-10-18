<?php

declare(strict_types=1);

use Src\Reporting\Application\ReportViewer;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\DateRange;
use Src\Reporting\Domain\DoctorFeeRule;
use Src\Reporting\Domain\InvalidReportRequest;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportScope;
use Src\Reporting\Infrastructure\Export\CellFormatter;

describe('LAP rentang tanggal laporan', function () {
    it('LAP menerima rentang sah dan menghitung hari serta bulan yang tersentuh', function () {
        $range = DateRange::between('2025-01-30', '2025-03-02');

        expect($range->days())->toBe(32)
            ->and($range->months())->toBe(['2025-01', '2025-02', '2025-03'])
            ->and($range->dates()[0])->toBe('2025-01-30')
            ->and(DateRange::day('2025-06-16')->days())->toBe(1);
    });

    it('LAP menolak tanggal akhir sebelum tanggal awal dengan kode stabil', function () {
        expect(fn () => DateRange::between('2025-06-20', '2025-06-10'))->toThrow(InvalidReportRequest::class, 'Tanggal akhir tidak boleh sebelum tanggal awal.');
        try {
            DateRange::between('2025-06-20', '2025-06-10');
        } catch (InvalidReportRequest $e) {
            expect($e->businessCode())->toBe('report.range_invalid')->and($e->httpStatus())->toBe(422);
        }
    });

    it('LAP menolak tanggal yang tidak ada di kalender dan format salah', function () {
        expect(fn () => DateRange::day('2025-02-30'))->toThrow(InvalidReportRequest::class)
            ->and(fn () => DateRange::day('16/06/2025'))->toThrow(InvalidReportRequest::class)
            ->and(fn () => DateRange::day(''))->toThrow(InvalidReportRequest::class);
    });

    it('LAP membatasi panjang rentang 366 hari agar agregasi muat dalam batas waktu hosting', function () {
        expect(DateRange::between('2024-01-01', '2024-12-31')->days())->toBe(366);
        expect(fn () => DateRange::between('2024-01-01', '2025-01-01'))->toThrow(InvalidReportRequest::class, 'paling panjang 366 hari');
    });

    it('LAP rentang sebelumnya sama panjang dan berakhir sehari sebelum rentang ini', function () {
        $previous = DateRange::between('2025-06-10', '2025-06-16')->previous();

        expect($previous->from)->toBe('2025-06-03')->and($previous->to)->toBe('2025-06-09')->and($previous->days())->toBe(7);
    });
});

describe('LAP aturan jasa dokter (Q-11)', function () {
    $scheme = ['default' => ['mode' => 'percentage', 'value' => 4000], 'overrides' => ['TAMBAL' => ['mode' => 'fixed', 'value' => 50000]]];

    it('LAP persentase dihitung dari nilai tindakan dengan pembulatan setengah ke atas', function () use ($scheme) {
        $rule = DoctorFeeRule::forProcedure($scheme, 'CABUT');

        expect($rule->feeFor(150000, 1)->amount)->toBe(60000)
            ->and($rule->label())->toBe('40%')
            ->and(DoctorFeeRule::fromArray(['mode' => 'percentage', 'value' => 3333])->feeFor(1001, 1)->amount)->toBe(334)
            ->and(DoctorFeeRule::fromArray(['mode' => 'percentage', 'value' => 1250])->label())->toBe('12,5%');
    });

    it('LAP jumlah tetap dikalikan jumlah tindakan dan pengecualian per kode menang atas bawaan', function () use ($scheme) {
        $rule = DoctorFeeRule::forProcedure($scheme, 'TAMBAL');

        expect($rule->mode)->toBe(DoctorFeeRule::FIXED)
            ->and($rule->feeFor(200000, 2)->amount)->toBe(100000)
            ->and($rule->label())->toBe('Rp 50.000 per tindakan');
    });

    it('LAP dokter tanpa skema atau skema rusak berjasa nol', function () {
        expect(DoctorFeeRule::forProcedure(null, 'CABUT')->feeFor(150000, 1)->amount)->toBe(0)
            ->and(DoctorFeeRule::forProcedure(['default' => 'rusak'], 'CABUT')->feeFor(150000, 1)->amount)->toBe(0)
            ->and(DoctorFeeRule::fromArray(['mode' => 'fixed', 'value' => -5])->feeFor(1, 3)->amount)->toBe(0);
    });
});

describe('LAP hak akses laporan menurut izin (matriks PRD 00)', function () {
    $scope = fn (array $permissions, ReportKey $key) => (new ReportViewer('U', $permissions))->scopeFor($key);

    it('LAP Pemilik dan Admin melihat semua laporan secara penuh', function () use ($scope) {
        $izin = ['report.view', 'report.financial', 'report.clinical', 'audit.view'];
        foreach (ReportKey::cases() as $key) {
            expect($scope($izin, $key))->toBe(ReportScope::Full);
        }
    });

    it('LAP Kasir hanya kas sendiri: ringkasan harian, piutang, dan tutup kas dengan cakupan sendiri', function () use ($scope) {
        $izin = ['report.view', 'report.financial.own'];
        $dilihat = array_values(array_filter(ReportKey::cases(), fn (ReportKey $k) => $scope($izin, $k) !== null));

        expect($dilihat)->toBe([ReportKey::DailySummary, ReportKey::Receivables, ReportKey::CashClosing])
            ->and($scope($izin, ReportKey::CashClosing))->toBe(ReportScope::Own)
            ->and($scope($izin, ReportKey::Revenue))->toBeNull();
    });

    it('LAP Dokter hanya tindakan dan kelengkapan rekam medis miliknya', function () use ($scope) {
        $izin = ['report.view', 'report.clinical.own'];

        expect($scope($izin, ReportKey::Procedures))->toBe(ReportScope::Own)
            ->and($scope($izin, ReportKey::RecordCompleteness))->toBe(ReportScope::Own)
            ->and($scope($izin, ReportKey::Visits))->toBeNull()
            ->and($scope($izin, ReportKey::Revenue))->toBeNull();
    });

    it('LAP Perawat melihat laporan stok lewat stock.manage, tanpa laporan lain', function () use ($scope) {
        $izin = ['stock.manage'];
        $dilihat = array_values(array_filter(ReportKey::cases(), fn (ReportKey $k) => $scope($izin, $k) !== null));

        expect($dilihat)->toBe([ReportKey::Stock]);
    });

    it('LAP tanpa izin laporan tidak melihat apa pun (bawaan aman)', function () {
        expect((new ReportViewer('U', ['patient.view', 'invoice.view']))->visibleReports())->toBe([]);
    });

    it('LAP kunci laporan unik dan kode berurutan LAP-01 sampai LAP-12', function () {
        $codes = array_map(fn (ReportKey $k) => $k->code(), ReportKey::cases());

        expect($codes)->toBe(['LAP-01', 'LAP-02', 'LAP-03', 'LAP-04', 'LAP-05', 'LAP-06', 'LAP-07', 'LAP-08', 'LAP-09', 'LAP-10', 'LAP-11', 'LAP-12'])
            ->and(array_unique(array_map(fn (ReportKey $k) => $k->value, ReportKey::cases())))->toHaveCount(12);
    });

    it('LAP laporan keuangan menyediakan filter dokter dan metode bayar, laporan klinis hanya dokter', function () {
        expect(ReportKey::Revenue->usesDoctorFilter())->toBeTrue()->and(ReportKey::Revenue->usesMethodFilter())->toBeTrue()
            ->and(ReportKey::Visits->usesDoctorFilter())->toBeTrue()->and(ReportKey::Visits->usesMethodFilter())->toBeFalse()
            ->and(ReportKey::CashClosing->usesMethodFilter())->toBeFalse();
    });
});

describe('LAP format sel untuk PDF', function () {
    it('LAP uang, persen, tanggal, dan menit sesuai M18', function () {
        expect(CellFormatter::text(ColumnKind::Money, 1250000))->toBe('Rp 1.250.000')
            ->and(CellFormatter::text(ColumnKind::Money, -30000))->toBe('-Rp 30.000')
            ->and(CellFormatter::text(ColumnKind::Percent, 8550))->toBe('85,5%')
            ->and(CellFormatter::text(ColumnKind::Percent, 10000))->toBe('100%')
            ->and(CellFormatter::text(ColumnKind::Date, '2025-06-16'))->toBe('16/06/2025')
            ->and(CellFormatter::text(ColumnKind::DateTime, '2025-06-16 09:05'))->toBe('16/06/2025 09:05')
            ->and(CellFormatter::text(ColumnKind::Minutes, 15))->toBe('15 mnt')
            ->and(CellFormatter::text(ColumnKind::Integer, 12500))->toBe('12.500')
            ->and(CellFormatter::text(ColumnKind::Money, null))->toBe('');
    });
});
