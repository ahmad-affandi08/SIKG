<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Domain\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Kunjungan berjalan dengan tindakan per gigi (Rp 250.000) dan per permukaan (Rp 100.000, hasil Tambalan) di katalog.
 *
 * @return array<string, mixed>
 */
function siapRencana(): array
{
    $s = siapKunjungan();
    $s['kasir'] = makeUser(Role::Kasir, $s['klinik']);
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    $s['gigi'] = tindakan($s, ['code' => 'CABUT-X', 'name' => 'Pencabutan', 'scope' => 'tooth', 'default_price_amount' => 250000]);
    $s['permukaan'] = tindakan($s, ['code' => 'TAMBAL-X', 'name' => 'Tambal', 'scope' => 'surface', 'default_price_amount' => 100000, 'result_condition_code' => 'TAM']);
    $s['kunjungi'] = tindakan($s, ['code' => 'SCALING-X', 'name' => 'Scaling', 'scope' => 'visit', 'default_price_amount' => 80000]);

    return $s;
}

function buatRencana(array $s, string $judul = 'Perawatan gigi belakang'): string
{
    tenant()->set($s['klinik']->id);
    test()->actingAs($s['dokter_user'])->post("/patients/{$s['pasien']}/treatment-plans", ['title' => $judul])->assertSessionHasNoErrors();

    return DB::table('treatment_plans')->where('patient_id', $s['pasien'])->orderByDesc('created_at')->orderByDesc('id')->value('id');
}

function tambahLangkah(array $s, string $rencana, object $tindakan, array $o = [])
{
    tenant()->set($s['klinik']->id);

    return test()->actingAs($s['dokter_user'])->post("/treatment-plans/{$rencana}/items", ['procedure_id' => $tindakan->id, 'quantity' => 1, ...$o]);
}

it('RM-06 dokter membuat rencana dengan langkah multi-kunjungan dan estimasi biaya dari tarif saat ini', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);

    tambahLangkah($s, $rencana, $s['gigi'], ['tooth_fdi' => 46, 'quantity' => 2, 'notes' => 'Kedua akar'])->assertSessionHasNoErrors();
    tambahLangkah($s, $rencana, $s['kunjungi'])->assertSessionHasNoErrors();

    $langkah = DB::table('treatment_plan_items')->where('plan_id', $rencana)->orderBy('sequence')->get();
    expect($langkah)->toHaveCount(2)
        ->and($langkah[0]->sequence)->toBe(1)->and($langkah[0]->unit_price_amount)->toBe(250000)->and($langkah[0]->estimate_amount)->toBe(500000)->and($langkah[0]->status)->toBe('planned')
        ->and($langkah[1]->sequence)->toBe(2)->and($langkah[1]->estimate_amount)->toBe(80000);

    test()->actingAs($s['dokter_user'])->get("/patients/{$s['pasien']}/treatment-plans")->assertOk()->assertInertia(function ($p) {
        $p->component('TreatmentPlans/Index')->where('plans.0.status', 'planned')->where('plans.0.estimate_amount', 580000)->where('plans.0.remaining_amount', 580000)->has('plans.0.items', 2)->where('can.manage', true);

        return $p;
    });
});

it('RM-06 perubahan tarif katalog tidak mengubah estimasi langkah yang sudah dibuat (MST-08)', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);
    tambahLangkah($s, $rencana, $s['gigi'], ['tooth_fdi' => 46])->assertSessionHasNoErrors();

    DB::table('procedures')->where('id', $s['gigi']->id)->update(['default_price_amount' => 999000]);

    expect(DB::table('treatment_plan_items')->where('plan_id', $rencana)->value('estimate_amount'))->toBe(250000);
});

it('RM-06 status rencana diturunkan dari status langkah: Direncanakan, Berjalan, Selesai', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);
    tambahLangkah($s, $rencana, $s['gigi'], ['tooth_fdi' => 46]);
    tambahLangkah($s, $rencana, $s['kunjungi']);
    [$a, $b] = DB::table('treatment_plan_items')->where('plan_id', $rencana)->orderBy('sequence')->pluck('id')->all();
    $status = fn () => DB::table('treatment_plans')->where('id', $rencana)->value('status');
    $ubah = fn (string $item, string $nilai) => test()->actingAs($s['dokter_user'])->post("/treatment-plans/{$rencana}/items/{$item}/status", ['status' => $nilai])->assertSessionHasNoErrors();

    expect($status())->toBe('planned');
    $ubah($a, 'in_progress');
    expect($status())->toBe('in_progress');
    $ubah($a, 'done');
    expect($status())->toBe('in_progress')->and(DB::table('treatment_plan_items')->where('id', $a)->value('completed_at'))->not->toBeNull();
    $ubah($b, 'done');
    expect($status())->toBe('done');
    // Menambah langkah baru ke rencana yang selesai membukanya kembali.
    tambahLangkah($s, $rencana, $s['kunjungi']);
    expect($status())->toBe('in_progress');
    $ubah($a, 'planned');
    expect(DB::table('treatment_plan_items')->where('id', $a)->value('completed_at'))->toBeNull();
});

it('RM-06 hanya langkah Direncanakan yang dapat dihapus (soft delete) dan estimasi ikut turun', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);
    tambahLangkah($s, $rencana, $s['gigi'], ['tooth_fdi' => 46]);
    tambahLangkah($s, $rencana, $s['kunjungi']);
    [$a, $b] = DB::table('treatment_plan_items')->where('plan_id', $rencana)->orderBy('sequence')->pluck('id')->all();

    test()->actingAs($s['dokter_user'])->post("/treatment-plans/{$rencana}/items/{$a}/status", ['status' => 'in_progress']);
    test()->actingAs($s['dokter_user'])->delete("/treatment-plans/{$rencana}/items/{$a}")->assertSessionHas('error');
    test()->actingAs($s['dokter_user'])->delete("/treatment-plans/{$rencana}/items/{$b}")->assertSessionHasNoErrors();

    expect(DB::table('treatment_plan_items')->where('id', $b)->value('deleted_at'))->not->toBeNull()
        ->and(DB::table('treatment_plan_items')->where('id', $b)->exists())->toBeTrue();
    test()->actingAs($s['dokter_user'])->get("/patients/{$s['pasien']}/treatment-plans")->assertInertia(fn ($p) => $p->has('plans.0.items', 1)->where('plans.0.estimate_amount', 250000));
});

it('RM-06 validasi: judul wajib, tindakan per gigi wajib bernomor gigi, jumlah 1 sampai 99', function () {
    $s = siapRencana();
    test()->actingAs($s['dokter_user'])->post("/patients/{$s['pasien']}/treatment-plans", ['title' => ''])->assertSessionHasErrors('title');
    $rencana = buatRencana($s);

    tambahLangkah($s, $rencana, $s['gigi'])->assertSessionHas('error');
    tambahLangkah($s, $rencana, $s['gigi'], ['tooth_fdi' => 99])->assertSessionHas('error');
    tambahLangkah($s, $rencana, $s['kunjungi'], ['quantity' => 0])->assertSessionHasErrors('quantity');
    tambahLangkah($s, $rencana, $s['kunjungi'], ['quantity' => 100])->assertSessionHasErrors('quantity');
    expect(DB::table('treatment_plan_items')->where('plan_id', $rencana)->count())->toBe(0);
});

it('RM-06 rencana yang dibatalkan tidak dapat diubah lagi dan rencana selesai tidak dapat dibatalkan', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);
    tambahLangkah($s, $rencana, $s['kunjungi']);

    test()->actingAs($s['dokter_user'])->post("/treatment-plans/{$rencana}/cancel")->assertSessionHasNoErrors();
    expect(DB::table('treatment_plans')->where('id', $rencana)->value('status'))->toBe('cancelled');
    tambahLangkah($s, $rencana, $s['kunjungi'])->assertSessionHas('error');

    $selesai = buatRencana($s, 'Rencana selesai');
    tambahLangkah($s, $selesai, $s['kunjungi']);
    $item = DB::table('treatment_plan_items')->where('plan_id', $selesai)->value('id');
    test()->actingAs($s['dokter_user'])->post("/treatment-plans/{$selesai}/items/{$item}/status", ['status' => 'done']);
    test()->actingAs($s['dokter_user'])->post("/treatment-plans/{$selesai}/cancel")->assertSessionHas('error');
});

it('RM-06 Dokter mengelola rencana; peran lain hanya melihat sesuai izin kunjungan', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);
    tenant()->set($s['klinik']->id);

    foreach (['perawat' => $s['perawat'], 'resepsionis' => $s['user'], 'kasir' => $s['kasir'], 'admin' => $s['admin']] as $user) {
        test()->actingAs($user)->post("/patients/{$s['pasien']}/treatment-plans", ['title' => 'Coba'])->assertForbidden();
        test()->actingAs($user)->post("/treatment-plans/{$rencana}/items", ['procedure_id' => $s['kunjungi']->id, 'quantity' => 1])->assertForbidden();
        test()->actingAs($user)->post("/treatment-plans/{$rencana}/cancel")->assertForbidden();
        test()->actingAs($user)->get("/patients/{$s['pasien']}/treatment-plans/create")->assertForbidden();
    }
    // Melihat: Dokter, Perawat, dan Admin punya encounter.view; Resepsionis dan Kasir tidak.
    test()->actingAs($s['perawat'])->get("/patients/{$s['pasien']}/treatment-plans")->assertOk()->assertInertia(fn ($p) => $p->where('can.manage', false));
    test()->actingAs($s['user'])->get("/patients/{$s['pasien']}/treatment-plans")->assertForbidden();
    test()->actingAs($s['kasir'])->get("/patients/{$s['pasien']}/treatment-plans")->assertForbidden();
});

it('RM-06 rencana tidak terlihat dan tidak dapat diubah dari klinik lain, dan pembukaannya tercatat di access log', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);
    $lain = makeClinic('Klinik Lain');
    $dokterLain = makeUser(Role::Dokter, $lain);
    tenant()->set($lain->id);

    test()->actingAs($dokterLain)->get("/patients/{$s['pasien']}/treatment-plans")->assertNotFound();
    test()->actingAs($dokterLain)->post("/treatment-plans/{$rencana}/cancel")->assertNotFound();

    tenant()->set($s['klinik']->id);
    test()->actingAs($s['dokter_user'])->get("/patients/{$s['pasien']}/treatment-plans")->assertOk();
    expect(DB::table('access_logs')->where('patient_id', $s['pasien'])->where('route', 'treatment-plans.index')->exists())->toBeTrue();
});

it('RM-06 perubahan rencana tercatat di audit log', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);
    tambahLangkah($s, $rencana, $s['kunjungi']);
    $item = DB::table('treatment_plan_items')->where('plan_id', $rencana)->value('id');
    test()->actingAs($s['dokter_user'])->post("/treatment-plans/{$rencana}/items/{$item}/status", ['status' => 'in_progress']);
    test()->actingAs($s['dokter_user'])->post("/treatment-plans/{$rencana}/cancel");

    $aksi = DB::table('audit_logs')->where('subject_id', $rencana)->orderBy('created_at')->pluck('action')->all();
    expect($aksi)->toContain('treatment_plan.created')->toContain('treatment_plan.item_added')->toContain('treatment_plan.item_status')->toContain('treatment_plan.cancelled');
});

it('RM-06 mengerjakan langkah dari layar pemeriksaan: formulir terisi, langkah selesai dan tertaut, tindakan masuk draft tagihan', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);
    tambahLangkah($s, $rencana, $s['gigi'], ['tooth_fdi' => 46, 'quantity' => 2, 'notes' => 'Kedua akar']);
    $item = DB::table('treatment_plan_items')->where('plan_id', $rencana)->value('id');
    tenant()->set($s['klinik']->id);

    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertOk()->assertInertia(fn ($p) => $p->has('plan_items', 1)->where('plan_items.0.id', $item));
    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}/procedures/create?plan_item={$item}")->assertOk()
        ->assertInertia(fn ($p) => $p->where('prefill.plan_item_id', $item)->where('prefill.procedure_id', $s['gigi']->id)->where('prefill.tooth_fdi', 46)->where('prefill.quantity', 2)->where('prefill.scope', 'tooth'));

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $s['gigi']->id, 'tooth_fdi' => 46, 'quantity' => 2, 'plan_item_id' => $item])->assertSessionHasNoErrors();

    $langkah = DB::table('treatment_plan_items')->where('id', $item)->first();
    expect($langkah->status)->toBe('done')->and($langkah->encounter_id)->toBe($s['kunjungan'])->and($langkah->completed_at)->not->toBeNull()
        ->and(DB::table('treatment_plans')->where('id', $rencana)->value('status'))->toBe('done')
        ->and(DB::table('invoice_items')->where('source_type', 'procedure')->count())->toBe(1);
    // Langkah selesai tidak lagi ditawarkan.
    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertInertia(fn ($p) => $p->has('plan_items', 0));
});

it('RM-06 menghapus tindakan hasil rencana mengembalikan langkah menjadi Direncanakan', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);
    tambahLangkah($s, $rencana, $s['gigi'], ['tooth_fdi' => 46]);
    $item = DB::table('treatment_plan_items')->where('plan_id', $rencana)->value('id');
    tenant()->set($s['klinik']->id);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $s['gigi']->id, 'tooth_fdi' => 46, 'quantity' => 1, 'plan_item_id' => $item])->assertSessionHasNoErrors();
    $entri = DB::table('encounter_procedures')->value('id');

    test()->actingAs($s['dokter_user'])->delete("/encounters/{$s['kunjungan']}/procedures/{$entri}")->assertRedirect();

    $langkah = DB::table('treatment_plan_items')->where('id', $item)->first();
    expect($langkah->status)->toBe('planned')->and($langkah->encounter_id)->toBeNull()->and($langkah->completed_at)->toBeNull()
        ->and(DB::table('treatment_plans')->where('id', $rencana)->value('status'))->toBe('planned');
});

it('RM-06 langkah milik pasien lain atau yang sudah selesai ditolak saat dikerjakan', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);
    tambahLangkah($s, $rencana, $s['kunjungi']);
    $item = DB::table('treatment_plan_items')->where('plan_id', $rencana)->value('id');
    $lain = makePatient($s['klinik'], 'Pasien Lain');
    DB::table('treatment_plans')->where('id', $rencana)->update(['patient_id' => $lain]);
    tenant()->set($s['klinik']->id);

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $s['kunjungi']->id, 'quantity' => 1, 'plan_item_id' => $item])->assertSessionHas('error');
    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}/procedures/create?plan_item={$item}")->assertNotFound();
    expect(DB::table('encounter_procedures')->count())->toBe(0);
});

it('RM-06 rencana ikut pindah ke pasien utama saat data pasien ganda digabung (PAS-07)', function () {
    $s = siapRencana();
    $rencana = buatRencana($s);
    $utama = makePatient($s['klinik'], 'Pasien Utama');
    tenant()->set($s['klinik']->id);

    test()->actingAs($s['admin'])->post("/patients/{$utama}/merge", ['source_id' => $s['pasien'], 'reason' => 'Pasien ganda', 'confirm' => true])->assertSessionHasNoErrors();

    expect(DB::table('treatment_plans')->where('id', $rencana)->value('patient_id'))->toBe($utama);
});

// --- ODO-05 ---------------------------------------------------------------------------------------------------------

function tambahTindakan(array $s, object $tindakan, array $o = [])
{
    tenant()->set($s['klinik']->id);

    return test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $tindakan->id, 'quantity' => 1, ...$o]);
}

function kondisiGigi(array $s): array
{
    $kondisi = [];
    foreach (DB::table('odontogram_tooth_states as t')->join('odontogram_snapshots as n', 'n.id', '=', 't.snapshot_id')->where('n.encounter_id', $s['kunjungan'])->get(['t.tooth_fdi', 't.surface', 't.condition_code']) as $r) {
        $kondisi["{$r->tooth_fdi}{$r->surface}"] = $r->condition_code;
    }

    ksort($kondisi);

    return $kondisi;
}

it('ODO-05 tindakan per permukaan yang menghasilkan kondisi ditawarkan ke dokter dan diterapkan setelah dikonfirmasi', function () {
    $s = siapRencana();
    tambahTindakan($s, $s['permukaan'], ['tooth_fdi' => 46, 'surfaces' => ['O', 'M']])->assertSessionHasNoErrors();
    $entri = DB::table('encounter_procedures')->value('id');

    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertInertia(fn ($p) => $p
        ->has('odontogram_suggestions', 1)->where('odontogram_suggestions.0.entry_id', $entri)->where('odontogram_suggestions.0.tooth_fdi', 46)
        ->where('odontogram_suggestions.0.surfaces', ['O', 'M'])->where('odontogram_suggestions.0.condition_code', 'TAM')->where('odontogram_suggestions.0.condition_name', 'Tambalan'));
    // Sebelum dikonfirmasi odontogram belum berubah.
    expect(kondisiGigi($s))->toBe([]);

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures/{$entri}/odontogram", ['action' => 'apply'])->assertSessionHasNoErrors();

    expect(kondisiGigi($s))->toBe(['46M' => 'TAM', '46O' => 'TAM'])
        ->and(DB::table('encounter_procedures')->where('id', $entri)->value('odontogram_status'))->toBe('applied')
        ->and(DB::table('audit_logs')->where('subject_id', $s['kunjungan'])->where('action', 'odontogram.suggestion_applied')->exists())->toBeTrue();
    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertInertia(fn ($p) => $p->has('odontogram_suggestions', 0));
});

it('ODO-05 dokter dapat mengabaikan usulan: odontogram tidak berubah dan usulan tidak muncul lagi', function () {
    $s = siapRencana();
    tambahTindakan($s, $s['permukaan'], ['tooth_fdi' => 46, 'surfaces' => ['O']]);
    $entri = DB::table('encounter_procedures')->value('id');

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures/{$entri}/odontogram", ['action' => 'dismiss'])->assertSessionHasNoErrors();

    expect(kondisiGigi($s))->toBe([])->and(DB::table('encounter_procedures')->where('id', $entri)->value('odontogram_status'))->toBe('dismissed');
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures/{$entri}/odontogram", ['action' => 'apply'])->assertSessionHas('error');
});

it('ODO-05 tindakan per gigi dengan kondisi seluruh gigi menandai seluruh gigi', function () {
    $s = siapRencana();
    $mahkota = tindakan($s, ['code' => 'MHK-X', 'name' => 'Pasang mahkota', 'scope' => 'tooth', 'default_price_amount' => 1000000, 'result_condition_code' => 'MHK']);
    tambahTindakan($s, $mahkota, ['tooth_fdi' => 36]);
    $entri = DB::table('encounter_procedures')->value('id');

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures/{$entri}/odontogram", ['action' => 'apply'])->assertSessionHasNoErrors();

    expect(kondisiGigi($s))->toBe(['36W' => 'MHK']);
});

it('ODO-05 usulan tidak muncul bila kondisi tidak cocok dengan cakupan tindakan atau tindakan tanpa kondisi hasil', function () {
    $s = siapRencana();
    // Kondisi Tambalan hanya per permukaan, sedangkan tindakan ini per gigi: tidak ada usulan.
    $salah = tindakan($s, ['code' => 'TAM-SALAH', 'name' => 'Tambal per gigi', 'scope' => 'tooth', 'default_price_amount' => 1, 'result_condition_code' => 'TAM']);
    tambahTindakan($s, $salah, ['tooth_fdi' => 11]);
    tambahTindakan($s, $s['gigi'], ['tooth_fdi' => 12]);
    tambahTindakan($s, $s['kunjungi']);

    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertInertia(fn ($p) => $p->has('odontogram_suggestions', 0));
});

it('ODO-05 kode kondisi hasil disalin saat tindakan dicatat sehingga perubahan katalog tidak memengaruhi tindakan lama', function () {
    $s = siapRencana();
    tambahTindakan($s, $s['permukaan'], ['tooth_fdi' => 46, 'surfaces' => ['O']]);
    DB::table('procedures')->where('id', $s['permukaan']->id)->update(['result_condition_code' => null]);

    expect(DB::table('encounter_procedures')->value('result_condition_code'))->toBe('TAM');
    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertInertia(fn ($p) => $p->has('odontogram_suggestions', 1));
});

it('ODO-05 hanya pemegang odontogram.write yang memutuskan, dan setelah kunjungan ditutup tidak ada usulan', function () {
    $s = siapRencana();
    tambahTindakan($s, $s['permukaan'], ['tooth_fdi' => 46, 'surfaces' => ['O']]);
    $entri = DB::table('encounter_procedures')->value('id');
    tenant()->set($s['klinik']->id);

    test()->actingAs($s['perawat'])->post("/encounters/{$s['kunjungan']}/procedures/{$entri}/odontogram", ['action' => 'apply'])->assertForbidden();
    test()->actingAs($s['user'])->post("/encounters/{$s['kunjungan']}/procedures/{$entri}/odontogram", ['action' => 'apply'])->assertForbidden();
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures/{$entri}/odontogram", ['action' => 'selesai'])->assertSessionHasErrors('action');

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertInertia(fn ($p) => $p->has('odontogram_suggestions', 0));
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures/{$entri}/odontogram", ['action' => 'apply'])->assertSessionHas('error');
    expect(kondisiGigi($s))->toBe([]);
});

it('ODO-05 Admin mengatur kondisi hasil tindakan di katalog, hanya kondisi aktif klinik itu', function () {
    $s = siapRencana();
    tenant()->set($s['klinik']->id);
    $data = ['code' => 'TMB-9', 'name' => 'Tambal baru', 'category' => 'Restorasi', 'scope' => 'surface', 'default_price_amount' => '120000'];

    test()->actingAs($s['admin'])->post('/settings/master/procedures', [...$data, 'result_condition_code' => 'TAM'])->assertSessionHasNoErrors();
    expect(DB::table('procedures')->where('code', 'TMB-9')->value('result_condition_code'))->toBe('TAM');
    test()->actingAs($s['admin'])->post('/settings/master/procedures', [...$data, 'code' => 'TMB-10', 'result_condition_code' => 'NGAWUR'])->assertSessionHasErrors('result_condition_code');
    test()->actingAs($s['admin'])->post('/settings/master/procedures', [...$data, 'code' => 'TMB-11', 'result_condition_code' => ''])->assertSessionHasNoErrors();
    expect(DB::table('procedures')->where('code', 'TMB-11')->value('result_condition_code'))->toBeNull();
});
