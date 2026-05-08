<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __invoke(Request $request, WhatsappBot $bot): JsonResponse
    {
        $expected = (string) config('whatsapp.webhook_token');
        $provided = (string) $request->query('token', $request->input('token', ''));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['ok' => false, 'error' => 'invalid token'], 401);
        }

        // Fonnte payload keys: device, sender, message, name, member, url, filename, extension, location
        $sender = (string) $request->input('sender', $request->input('from', ''));
        $message = (string) $request->input('message', $request->input('text', ''));
        $name = (string) $request->input('name', '');
        $url = (string) $request->input('url', '');

        Log::channel('single')->info('[WA←IN] payload', $request->except(['token']));

        if ($sender === '') {
            return response()->json(['ok' => false, 'error' => 'sender required'], 422);
        }

        // If only media (no text), give a helpful reply for now.
        if ($message === '' && $url !== '') {
            $message = '/help';
        }

        if ($message === '') {
            return response()->json(['ok' => true, 'note' => 'empty message ignored']);
        }

        // Idempotency: Fonnte may retry on timeout. Drop duplicates within 60s window.
        $idempotencyKey = 'wa:in:'.sha1($sender.'|'.$message);
        if (! Cache::add($idempotencyKey, 1, 60)) {
            return response()->json(['ok' => true, 'note' => 'duplicate ignored']);
        }

        $result = $bot->handleIncoming($sender, $message);

        return response()->json([
            'ok' => true,
            'sender' => $sender,
            'name' => $name,
            'matched_user_id' => $result['user_id'],
            'reply' => $result['reply'],
            'transaction_id' => $result['transaction_id'],
        ]);
    }
}
