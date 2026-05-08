<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteClient
{
    public function send(string $phone, string $message): array
    {
        $sendReal = (bool) config('whatsapp.send_real');
        $target = $this->formatTarget($phone);

        if (! $sendReal) {
            Log::channel('single')->info('[WA→OUT] dev-mode (not sent)', [
                'to' => $target,
                'message' => $message,
            ]);

            return ['ok' => true, 'mode' => 'log', 'target' => $target];
        }

        $token = config('whatsapp.fonnte.token');
        if (! $token) {
            Log::warning('[WA] FONNTE_TOKEN missing; falling back to log', ['to' => $target]);

            return ['ok' => false, 'mode' => 'log', 'error' => 'no-token'];
        }

        $response = Http::asForm()
            ->withHeaders(['Authorization' => $token])
            ->post(config('whatsapp.fonnte.send_url'), [
                'target' => $target,
                'message' => $message,
                'countryCode' => '62',
            ]);

        $body = $response->json();
        Log::channel('single')->info('[WA→OUT] fonnte', [
            'to' => $target,
            'status' => $response->status(),
            'body' => $body,
        ]);

        return ['ok' => $response->successful(), 'mode' => 'fonnte', 'status' => $response->status(), 'body' => $body];
    }

    /**
     * Fonnte expects target without leading "+", and prefers the 62 prefix for Indonesian numbers.
     */
    private function formatTarget(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }
}
