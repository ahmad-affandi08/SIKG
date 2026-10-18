<?php

declare(strict_types=1);

namespace Src\Billing\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Billing\Application\Online\OnlinePayments;

/**
 * P3-05: webhook penyedia pembayaran, tanpa sesi dan tanpa CSRF (dipanggil server penyedia). Klinik dikenali dari token acak pada alamat,
 * tanda tangan notifikasi diperiksa, dan statusnya ditanyakan ulang ke penyedia sebelum pembayaran dicatat. Token yang tidak dikenal 404,
 * tanda tangan salah 403; keduanya tanpa rincian.
 */
final class PaymentWebhookController
{
    public function __construct(private readonly OnlinePayments $online) {}

    public function receive(Request $request, string $token): JsonResponse
    {
        $headers = [];
        foreach (['client-id', 'request-id', 'request-timestamp', 'signature'] as $name) {
            $headers[$name] = (string) $request->header($name, '');
        }
        $result = $this->online->handleWebhook($token, $headers, (string) $request->getContent(), '/'.$request->path());
        abort_if($result === null, 404);
        abort_if($result === false, 403);

        return response()->json(['ok' => true]);
    }
}
