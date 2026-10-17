<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Reports;

use Src\Reporting\Application\ReportQuery;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportColumn;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportMetric;
use Src\Reporting\Domain\ReportResult;
use Src\Reporting\Infrastructure\Queries\DoctorFeeQueries;

/**
 * LAP-03 dan P2-09: jasa dokter per periode menurut skema di master dokter. Dasar hitung: nilai tindakan pada tagihan final yang
 * terbit dalam rentang (setelah diskon item). Skema dapat bertingkat atas produksi bulanan dan membebankan sebagian biaya lab
 * pesanan dokter; jasa bersih = jasa kotor dikurangi potongan lab. Obat dan item manual tidak menghasilkan jasa.
 */
final readonly class DoctorFeesReport extends AbstractReport
{
    public function __construct(private DoctorFeeQueries $fees) {}

    public function key(): ReportKey
    {
        return ReportKey::DoctorFees;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $f = $query->filters;
        $statements = $this->fees->statements($f->range, $f->doctorId, $f->methodId);

        $detail = [];
        $summary = [];
        $monthly = [];
        $notes = [];
        foreach ($statements as $statement) {
            $doctor = $statement['doctor'];
            $row = ['doctor' => $doctor, 'qty' => 0, 'base' => 0, 'gross' => 0, 'lab' => 0, 'fee' => 0];
            foreach ($statement['months'] as $month) {
                $row['base'] += $month->base;
                $row['gross'] += $month->gross;
                $row['lab'] += $month->labDeduction;
                $row['fee'] += $month->net;
                foreach ($month->lines as $line) {
                    $row['qty'] += $line->quantity;
                    $key = $doctor.'|'.$line->code;
                    $detail[$key] ??= ['doctor' => $doctor, 'code' => $line->code, 'name' => $line->name, 'qty' => 0, 'base' => 0, 'rule' => $line->rule, 'fee' => 0];
                    $detail[$key]['qty'] += $line->quantity;
                    $detail[$key]['base'] += $line->base;
                    $detail[$key]['fee'] += $line->fee;
                }
                $monthly[] = [
                    'doctor' => $doctor, 'month' => $month->month, 'base' => $month->base, 'gross' => $month->gross, 'lab_cost' => $month->labCost,
                    'lab' => $month->labDeduction, 'fee' => $month->net, 'uncovered' => $month->uncovered,
                ];
            }
            $summary[] = $row;
            $notes[] = 'Skema '.$doctor.': '.$statement['scheme']->describe().'.';
        }
        $detail = array_values($detail);
        usort($detail, fn (array $a, array $b) => [$a['doctor'], $a['code']] <=> [$b['doctor'], $b['code']]);
        usort($summary, fn (array $a, array $b) => [$b['fee'], $a['doctor']] <=> [$a['fee'], $b['doctor']]);
        usort($monthly, fn (array $a, array $b) => [$a['doctor'], $a['month']] <=> [$b['doctor'], $b['month']]);

        $totalFee = array_sum(array_column($summary, 'fee'));
        $totalGross = array_sum(array_column($summary, 'gross'));
        $totalLab = array_sum(array_column($summary, 'lab'));
        $totalBase = array_sum(array_column($summary, 'base'));

        return new ReportResult($this->key(), $f->range, [
            new ReportMetric('Total jasa dokter', $totalFee, ColumnKind::Money),
            new ReportMetric('Jasa kotor', $totalGross, ColumnKind::Money),
            new ReportMetric('Potongan biaya lab', $totalLab, ColumnKind::Money),
            new ReportMetric('Nilai tindakan (dasar hitung)', $totalBase, ColumnKind::Money),
            new ReportMetric('Dokter dengan jasa', count($summary)),
        ], [
            $this->table(
                'Jasa per dokter',
                [
                    new ReportColumn('doctor', 'Dokter'), new ReportColumn('qty', 'Tindakan', ColumnKind::Integer), new ReportColumn('base', 'Nilai tindakan', ColumnKind::Money),
                    new ReportColumn('gross', 'Jasa kotor', ColumnKind::Money), new ReportColumn('lab', 'Potongan lab', ColumnKind::Money), new ReportColumn('fee', 'Jasa bersih', ColumnKind::Money),
                ],
                $summary,
                $summary === [] ? null : ['doctor' => 'Total', 'qty' => array_sum(array_column($summary, 'qty')), 'base' => $totalBase, 'gross' => $totalGross, 'lab' => $totalLab, 'fee' => $totalFee],
                'Belum ada tindakan pada tagihan final dalam rentang ini.',
            ),
            $this->table(
                'Rekap per bulan',
                [
                    new ReportColumn('doctor', 'Dokter'), new ReportColumn('month', 'Bulan'), new ReportColumn('base', 'Nilai tindakan', ColumnKind::Money),
                    new ReportColumn('gross', 'Jasa kotor', ColumnKind::Money), new ReportColumn('lab_cost', 'Biaya lab diterima', ColumnKind::Money),
                    new ReportColumn('lab', 'Potongan lab', ColumnKind::Money), new ReportColumn('fee', 'Jasa bersih', ColumnKind::Money), new ReportColumn('uncovered', 'Potongan tak tertutup', ColumnKind::Money),
                ],
                $monthly,
                null,
                'Belum ada tindakan pada tagihan final dalam rentang ini.',
            ),
            $this->table(
                'Rincian per tindakan',
                [
                    new ReportColumn('doctor', 'Dokter'), new ReportColumn('code', 'Kode'), new ReportColumn('name', 'Tindakan'),
                    new ReportColumn('qty', 'Jumlah', ColumnKind::Integer), new ReportColumn('base', 'Nilai tindakan', ColumnKind::Money),
                    new ReportColumn('rule', 'Aturan jasa'), new ReportColumn('fee', 'Jasa kotor', ColumnKind::Money),
                ],
                $detail,
                null,
                'Belum ada tindakan pada tagihan final dalam rentang ini.',
            ),
        ], [
            'Dasar hitung adalah nilai item tindakan pada tagihan final yang terbit dalam rentang, setelah diskon item; diskon tagihan keseluruhan tidak dibagi ke item.',
            'Aturan jasa dibaca dari skema di master dokter: persentase atau jumlah tetap per tindakan dengan pengecualian per kode, atau bertingkat atas produksi tindakan tanpa pengecualian pada tiap bulan terbit (persentase tiap tingkat berlaku untuk bagian produksi di dalam batasnya). Dokter tanpa skema berjasa nol.',
            'Potongan lab = bagian biaya lab (menurut skema dokter) dari pesanan lab dokter itu yang diterima pada bulan tersebut. Jasa bersih tidak kurang dari nol; potongan yang melebihi jasa bulan itu tampil di "Potongan tak tertutup" dan tidak dibawa ke bulan berikutnya.',
            'Jasa dihitung saat tagihan terbit, tanpa melihat apakah tagihan sudah lunas. Jasa yang dibatalkan ikut hilang karena tagihan batal tidak dihitung.',
            ...$notes,
        ]);
    }
}
