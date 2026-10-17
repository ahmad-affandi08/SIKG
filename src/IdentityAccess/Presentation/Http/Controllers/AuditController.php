<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\IdentityAccess\Application\Audit\AuditTrail;

/**
 * SYS-01: audit log dan jejak akses rekam medis. Izin audit.view dipasang di route (Pemilik dan Admin).
 */
final class AuditController
{
    public function index(Request $request, AuditTrail $trail): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'subject_type' => ['nullable', 'string', 'max:100'],
            'subject_id' => ['nullable', 'string', 'max:26'],
            'actor_id' => ['nullable', 'string', 'size:26'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Audit/Index', [
            ...$trail->list($filters, (int) ($filters['page'] ?? 1)),
            'filters' => [
                'action' => $filters['action'] ?? '',
                'subject_type' => $filters['subject_type'] ?? '',
                'subject_id' => $filters['subject_id'] ?? '',
                'actor_id' => $filters['actor_id'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }

    public function show(Request $request, AuditTrail $trail, string $entry): Response
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);
        $detail = $trail->detail($id, $entry);
        abort_if($detail === null, 404);

        return Inertia::render('Audit/Show', ['entry' => $detail]);
    }

    public function accesses(Request $request, AuditTrail $trail): Response
    {
        $filters = $request->validate([
            'actor_id' => ['nullable', 'string', 'size:26'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Audit/Accesses', [
            ...$trail->accesses($filters, (int) ($filters['page'] ?? 1)),
            'filters' => ['actor_id' => $filters['actor_id'] ?? '', 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
        ]);
    }
}
