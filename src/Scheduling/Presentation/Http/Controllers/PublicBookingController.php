<?php

declare(strict_types=1);

namespace Src\Scheduling\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Scheduling\Application\Booking\PublicBooking;

/**
 * P3-01: halaman booking publik. Tanpa login; klinik dari alamat halaman (middleware ResolveBookingClinic), dibatasi throttle di route.
 * Alamat status memuat token acak, bukan data pasien.
 */
final class PublicBookingController
{
    public function __construct(private readonly PublicBooking $booking) {}

    public function index(string $slug): Response
    {
        return Inertia::render('Booking/Public/Index', ['slug' => $slug, ...$this->booking->portal()]);
    }

    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate(['doctor_id' => ['required', 'string', 'size:26'], 'date' => ['required', 'date_format:Y-m-d']]);

        return response()->json(['data' => $this->booking->slots($data['doctor_id'], $data['date']), 'meta' => []])->header('Cache-Control', 'no-store');
    }

    public function submit(Request $request, string $slug): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', 'max:25'],
            'doctor_id' => ['required', 'string', 'size:26'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'complaint' => ['nullable', 'string', 'max:300'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.', 'name.min' => 'Nama lengkap minimal 3 karakter.', 'phone.required' => 'Nomor HP wajib diisi.',
            'doctor_id.required' => 'Pilih dokter.', 'date.required' => 'Pilih tanggal.', 'time.required' => 'Pilih jam.', 'consent.accepted' => 'Setujui penggunaan nomor HP untuk konfirmasi janji.',
            'website.max' => 'Permintaan tidak dapat diproses.',
        ]);

        $result = $this->booking->submit($data['name'], $data['phone'], $data['doctor_id'], $data['date'], $data['time'], $data['complaint'] ?? null, $request->ip());

        return redirect("/book/{$slug}/r/{$result['token']}");
    }

    public function status(string $slug, string $token): Response
    {
        return Inertia::render('Booking/Public/Status', ['slug' => $slug, 'token' => $token, 'booking' => $this->booking->status($token)]);
    }

    public function verify(Request $request, string $slug, string $token): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']], ['code.required' => 'Kode verifikasi wajib diisi.', 'code.regex' => 'Kode verifikasi terdiri dari 6 angka.']);
        $this->booking->verify($token, $data['code']);

        return redirect("/book/{$slug}/r/{$token}")->with('success', 'Nomor terverifikasi. Permintaan Anda menunggu konfirmasi klinik.');
    }

    public function resend(string $slug, string $token): RedirectResponse
    {
        $this->booking->resendCode($token);

        return redirect("/book/{$slug}/r/{$token}")->with('success', 'Kode baru dikirim.');
    }

    public function cancel(string $slug, string $token): RedirectResponse
    {
        $this->booking->cancel($token);

        return redirect("/book/{$slug}/r/{$token}")->with('success', 'Permintaan dibatalkan.');
    }
}
