<?php

namespace App\Console\Commands\Whatsapp;

use App\Services\Whatsapp\FonnteClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kasku:wa:send-test {target : phone number, e.g. 6281...} {message=Halo dari Kasku Bot 👋 (test)}')]
#[Description('Send a real WhatsApp test message via Fonnte (forces send_real=true for this call).')]
class SendTest extends Command
{
    public function handle(FonnteClient $fonnte): int
    {
        $target = (string) $this->argument('target');
        $message = (string) $this->argument('message');

        if (! config('whatsapp.fonnte.token')) {
            $this->error('FONNTE_TOKEN is empty. Set it in .env first.');
            return self::FAILURE;
        }

        config()->set('whatsapp.send_real', true);

        $this->line("→ Sending to {$target}…");
        $result = $fonnte->send($target, $message);

        $this->line('Result:');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
