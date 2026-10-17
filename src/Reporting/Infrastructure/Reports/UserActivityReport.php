<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Reports;

use Src\Reporting\Application\ReportQuery;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportColumn;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportMetric;
use Src\Reporting\Domain\ReportResult;
use Src\Reporting\Infrastructure\Queries\ReportSource;

/**
 * LAP-10: ringkasan audit log per pengguna dan per jenis aksi, ditambah jumlah pembukaan rekam medis pasien dari jejak akses.
 * Hanya angka; isi sebelum dan sesudah perubahan tetap di halaman Audit log.
 */
final readonly class UserActivityReport extends AbstractReport
{
    private const SYSTEM = 'sistem';

    public function __construct(private ReportSource $source) {}

    public function key(): ReportKey
    {
        return ReportKey::UserActivity;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $f = $query->filters;

        $auditBase = $this->source->from('audit_logs', 'a', false);
        $this->source->within($auditBase, 'a.created_at', $f->range);
        $audit = (clone $auditBase)->groupBy('a.actor_id')
            ->selectRaw('a.actor_id as actor_id, COUNT(*) as n, MIN(a.created_at) as first_at, MAX(a.created_at) as last_at')->get();
        $actions = (clone $auditBase)->groupBy('a.action')->orderByRaw('COUNT(*) DESC')->orderBy('a.action')
            ->selectRaw('a.action as action, COUNT(*) as n, COUNT(DISTINCT a.actor_id) as users')->get();

        $accessBase = $this->source->from('access_logs', 'l', false);
        $this->source->within($accessBase, 'l.at', $f->range);
        $access = $accessBase->groupBy('l.actor_id')->selectRaw('l.actor_id as actor_id, COUNT(*) as n, COUNT(DISTINCT l.patient_id) as patients')->get();

        $by = [];
        foreach ($audit as $r) {
            $id = (string) ($r->actor_id ?? self::SYSTEM);
            $by[$id] = ['user_id' => $id, 'actions' => (int) $r->n, 'first' => (string) $r->first_at, 'last' => (string) $r->last_at, 'opened' => 0, 'patients' => 0];
        }
        foreach ($access as $r) {
            $id = (string) ($r->actor_id ?? self::SYSTEM);
            $by[$id] ??= ['user_id' => $id, 'actions' => 0, 'first' => null, 'last' => null, 'opened' => 0, 'patients' => 0];
            $by[$id]['opened'] = (int) $r->n;
            $by[$id]['patients'] = (int) $r->patients;
        }
        $names = $this->source->names('users', array_keys($by));

        $users = [];
        foreach ($by as $id => $row) {
            $users[] = [
                'user' => $id === self::SYSTEM ? 'Sistem' : ($names[$id] ?? '(pengguna tidak dikenal)'), 'actions' => $row['actions'], 'opened' => $row['opened'], 'patients' => $row['patients'],
                'first' => $this->source->localTime($row['first']), 'last' => $this->source->localTime($row['last']),
            ];
        }
        usort($users, fn (array $a, array $b) => [$b['actions'], $a['user']] <=> [$a['actions'], $b['user']]);

        $actionRows = [];
        foreach ($actions as $r) {
            $actionRows[] = ['action' => (string) $r->action, 'count' => (int) $r->n, 'users' => (int) $r->users];
        }

        $totalActions = array_sum(array_column($users, 'actions'));
        $totalOpened = array_sum(array_column($users, 'opened'));

        return new ReportResult($this->key(), $f->range, [
            new ReportMetric('Aksi tercatat', $totalActions),
            new ReportMetric('Pengguna aktif', count(array_filter($users, fn (array $u) => $u['user'] !== 'Sistem'))),
            new ReportMetric('Rekam medis dibuka', $totalOpened),
            new ReportMetric('Jenis aksi', count($actionRows)),
        ], [
            $this->table(
                'Aktivitas per pengguna',
                [
                    new ReportColumn('user', 'Pengguna'), new ReportColumn('actions', 'Aksi tercatat', ColumnKind::Integer), new ReportColumn('opened', 'Rekam medis dibuka', ColumnKind::Integer),
                    new ReportColumn('patients', 'Pasien berbeda', ColumnKind::Integer), new ReportColumn('first', 'Aksi pertama', ColumnKind::DateTime), new ReportColumn('last', 'Aksi terakhir', ColumnKind::DateTime),
                ],
                $users,
                $users === [] ? null : ['user' => 'Total', 'actions' => $totalActions, 'opened' => $totalOpened, 'patients' => null, 'first' => null, 'last' => null],
                'Belum ada aktivitas tercatat pada rentang ini.',
            ),
            $this->table(
                'Aktivitas per jenis aksi',
                [new ReportColumn('action', 'Aksi'), new ReportColumn('count', 'Jumlah', ColumnKind::Integer), new ReportColumn('users', 'Pengguna', ColumnKind::Integer)],
                $actionRows,
                null,
                'Belum ada aktivitas tercatat pada rentang ini.',
            ),
        ], [
            'Sumber: audit log (perubahan data penting) dan jejak akses rekam medis. Aksi tanpa pengguna adalah proses sistem.',
        ]);
    }
}
