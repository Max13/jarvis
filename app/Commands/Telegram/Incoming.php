<?php

namespace App\Commands\Telegram;

use App\Jobs\ProcessTelegramDocument;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

class Incoming extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'telegram:incoming {payload}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Queue incoming message from Telegram';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $payload = json_decode($this->argument('payload'), true);

        if (
               is_null($payload)
            || !in_array($payload['message']['from']['id'], config('telegram.allowed'))
        ) {
            return 1;
        }

        if (isset($payload['message']['document'])) {
            ProcessTelegramDocument::dispatch($payload);

            Http::post(
                config('telegram.endpoint').'/bot'.config('telegram.token').'/sendmessage',
                [
                    'chat_id' => $payload['message']['chat']['id'],
                    'text' => 'Accepted',
                    'reply_to_message_id' => $payload['message']['message_id'],
                    'allow_sending_without_reply' => true,
                ]
            );

            $this->info('Payload dispatched to queue');
        }
    }
}
