<?php

namespace App\Console\Commands\Whatsapp;

use App\Services\Whatsapp\WhatsappBot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kasku:wa:simulate {sender : phone (+62...)} {message : message body}')]
#[Description('Simulate an incoming WhatsApp message and pipe through the bot')]
class SimulateMessage extends Command
{
    public function handle(WhatsappBot $bot): int
    {
        $sender = (string) $this->argument('sender');
        $message = (string) $this->argument('message');

        $result = $bot->handleIncoming($sender, $message);

        $this->info("From {$sender}:");
        $this->line($message);
        $this->newLine();
        $this->info('Bot reply:');
        $this->line($result['reply']);

        if ($result['transaction_id']) {
            $this->newLine();
            $this->comment("Transaction #{$result['transaction_id']} created.");
        }

        return self::SUCCESS;
    }
}
