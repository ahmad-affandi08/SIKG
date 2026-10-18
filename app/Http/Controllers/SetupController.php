<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Setup\InstallStarterData;
use App\Setup\SetupProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Shared\Application\TenantContext;

/**
 * MST-10: halaman Penyiapan awal untuk Pemilik dan Admin (izin setting.manage): daftar periksa kemajuan dan tombol data awal.
 */
final class SetupController
{
    public function __construct(private readonly TenantContext $tenant, private readonly SetupProgress $progress) {}

    public function show(): Response
    {
        $steps = $this->progress->steps($this->clinicId());
        $required = array_values(array_filter($steps, fn (array $s) => $s['required']));

        return Inertia::render('Settings/Setup', [
            'steps' => $steps,
            'required_total' => count($required),
            'required_done' => count(array_filter($required, fn (array $s) => $s['done'])),
        ]);
    }

    public function starterData(Request $request, InstallStarterData $install): RedirectResponse
    {
        $actor = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($actor), 403);

        $result = $install($actor, $this->clinicId());
        $added = array_sum($result);

        return back()->with('success', $added === 0
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
