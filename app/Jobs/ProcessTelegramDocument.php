<?php

namespace App\Jobs;

use App\Exceptions\Telegram\UserNotAllowedException;
use App\Models\Telegram\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ProcessTelegramDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Payload received from Telegram
     *
     * @var array
     */
    protected $payload;

    /**
     * User sending the document
     *
     * @var \App\Models\Telegram\User
     */
    protected $user;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Telegram\User $user
     * @param  array                     $payload
     * @return void
     */
    public function __construct(User $user, array $payload)
    {
        $this->user = $user;
        $this->payload = $payload;
    }

    /**
     * Download the given url file, store it in a temporary file
     * then returns the local filename
     *
     * @param  string  $url
     * @return string|false
     */
    public function download($url)
    {
        $tmpFile = tempnam(null, 'jarvis_tg');

        $fdRemote = fopen($url, 'r');
        throw_if(
            $fdRemote === false,
            RuntimeException::class,
            "Couldn’t open remote url: $url"
        );

        $fdLocal = fopen($tmpFile, 'w+');
        throw_if(
            $fdLocal === false,
            RuntimeException::class,
            "Couldn’t open temporary file: $tmpFile"
        );

        while ($buffer = fread($fdRemote, 4096)) {
            fwrite($fdLocal, $buffer);
        }

        fclose($fdLocal);
        fclose($fdRemote);

        return $tmpFile;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->user->is_pending) {
            // If we haven't attempted max tries, release for 5 min
            if ($this->attempts() < $this->tries) {
                return $this->release(60 * 5);
            }

            throw new UserNotAllowedException('You are not allowed to use this bot');
        }

        $res = Http::get(
            config('telegram.endpoint').'/bot'.config('telegram.token').'/getfile',
            [
                'file_id' => $this->payload['message']['document']['file_id'],
            ]
        );

        $fileUrl = config('telegram.endpoint').'/file/bot'.config('telegram.token').'/'.$res['result']['file_path'];

        $sshFile = base_path('lpuser@kitt_rsa');
        $tmpFile = $this->download($fileUrl);

        exec(
            "ssh -i $sshFile -o 'UserKnownHostsFile /dev/null' -o 'StrictHostKeyChecking no' lpuser@kitt.home.rihan.fr lp < $tmpFile",
            $output,
            $result_code
        );

        throw_if(
            $result_code !== 0,
            RuntimeException::class,
            implode(PHP_EOL, $output)
        );

        if ($result_code === 0) {
            Http::post(
                config('telegram.endpoint').'/bot'.config('telegram.token').'/sendmessage',
                [
                    'chat_id' => $this->payload['message']['chat']['id'],
                    'text' => 'Sent to the printer',
                    'reply_to_message_id' => $this->payload['message']['message_id'],
                    'allow_sending_without_reply' => true,
                ]
            );
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception)
    {
        Http::post(
            config('telegram.endpoint').'/bot'.config('telegram.token').'/sendmessage',
            [
                'chat_id' => $this->payload['message']['chat']['id'],
                'text' => $exception->getMessage(),
                'reply_to_message_id' => $this->payload['message']['message_id'],
                'allow_sending_without_reply' => true,
            ]
        );
    }
}
