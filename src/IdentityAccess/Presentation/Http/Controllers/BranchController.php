<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Src\IdentityAccess\Application\ActiveBranch;

/**
 * P3-04: berpindah cabang aktif. Hanya pengguna yang tidak terikat satu cabang; aturannya ada di use case. Pilihan disimpan di sesi
 * dan dipulihkan tiap permintaan oleh ResolveTenant.
 */
final class BranchController
{
    public function switch(Request $request, ActiveBranch $active): RedirectResponse
    {
        $actorId = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($actorId), 403);
        $data = $request->validate(['branch_id' => ['nullable', 'string', 'size:26']]);
        $branchId = isset($data['branch_id']) && $data['branch_id'] !== '' ? $data['branch_id'] : null;

        $chosen = $active->choose($actorId, $branchId);
        if ($chosen === null) {
            $request->session()->forget(ActiveBranch::SESSION_KEY);
        } else {
            $request->session()->put(ActiveBranch::SESSION_KEY, $chosen);
        }

        return back()->with('success', $chosen === null ? 'Menampilkan semua cabang.' : 'Cabang aktif diganti.');
    }
}
