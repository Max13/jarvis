<?php

namespace App\Jobs;

use App\Exceptions\Telegram\UserNotAllowedException;
use App\Models\Telegram\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
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
     * Document received
     *
     * @var \App\Models\Telegram\Document
     */
    protected $document;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Telegram\Document $document
     * @return void
     */
    public function __construct(Document $document)
    {
        $this->document = $document;
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
        if ($this->document->user->is_pending) {
            // If we haven't attempted max tries, release for 5 min
            if ($this->attempts() < $this->tries) {
                return $this->release(60 * 5);
            }

            throw new UserNotAllowedException('You are not allowed to use this bot');
        }

        $res = Http::get(
            config('telegram.endpoint').'/bot'.config('telegram.token').'/getfile',
            [
                'file_id' => $this->document->file_id,
            ]
        );

        $fileUrl = config('telegram.endpoint').'/file/bot'.config('telegram.token').'/'.$res['result']['file_path'];

        $documentPath = $this->download($fileUrl);
        $sshFile = tap(tempnam('', ''), function ($tmp) {
            file_put_contents($tmp, config('ssh.key'));
        });

        if (App::isProduction()) {
            $command = config('ssh.command') . ' lp < ' . escapeshellarg($documentPath);
            $command = str_replace(
                [
                    '{SSH_KEY_FILE}',
                    '{SSH_PORT}',
                    '{SSH_USER}',
                    '{SSH_HOST}',
                ],
                [
                    escapeshellarg($sshFile),
                    escapeshellarg(config('ssh.port')),
                    escapeshellarg(config('ssh.user')),
                    escapeshellarg(config('ssh.host')),
                ],
                $command
            );

            file_put_contents(
                __DIR__.'/../../log.log',
                $command.PHP_EOL,
                FILE_APPEND
            );

            exec($command, $output, $result_code);

            file_put_contents(
                __DIR__.'/../../log.log',
                $result_code.' - '.json_encode($output).PHP_EOL.PHP_EOL,
                FILE_APPEND
            );
        } else {
            $result_code = 0;
            $output = [];
        }

        throw_if(
            $result_code !== 0,
            RuntimeException::class,
            implode(PHP_EOL, $output)
        );

        Http::post(
            config('telegram.endpoint').'/bot'.config('telegram.token').'/sendmessage',
            [
                'chat_id' => $this->document->chat_id,
                'text' => 'Sent to the printer',
                'reply_to_message_id' => $this->document->message_id,
                'allow_sending_without_reply' => true,
            ]
        );

        $this->document->delete();
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
                'chat_id' => $this->document->chat_id,
                'text' => $exception->getMessage(),
                'reply_to_message_id' => $this->document->message_id,
                'allow_sending_without_reply' => true,
            ]
        );
    }
}
