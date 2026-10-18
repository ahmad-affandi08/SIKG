<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Shared\Application\Setup\LoadStarterData;
use Src\Shared\Application\Setup\SetupOverview;
use Src\Shared\Application\TenantContext;

/**
 * MST-10: halaman Penyiapan awal untuk Pemilik dan Admin (izin setting.manage): daftar periksa kemajuan dan tombol data awal.
 */
final class SetupController
{
    public function __construct(private readonly TenantContext $tenant, private readonly SetupOverview $overview) {}

    public function show(): Response
    {
        $clinicId = $this->clinicId();
        $progress = $this->overview->requiredProgress($clinicId);

        return Inertia::render('Settings/Setup', [
            'steps' => array_map(fn ($step) => $step->toArray(), $this->overview->steps($clinicId)),
            'required_total' => $progress['total'],
            'required_done' => $progress['done'],
        ]);
    }

    public function starterData(Request $request, LoadStarterData $load): RedirectResponse
    {
        $actor = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($actor), 403);

        $result = $load($actor, $this->clinicId());

        return back()->with('success', array_sum($result) === 0
            ? 'Data awal sudah lengkap, tidak ada yang ditambahkan.'
            : "Data awal ditambahkan: {$result['procedures']} tindakan, {$result['medicines']} obat dan bahan, {$result['payment_methods']} metode pembayaran, {$result['expense_categories']} kategori biaya, {$result['holidays']} hari libur.");
    }

    private function clinicId(): string
    {
        $id = $this->tenant->clinicId();
        abort_if($id === null, 403);

        return $id;
    }
}
