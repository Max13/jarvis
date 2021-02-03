<?php

namespace App\Commands\Telegram;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

class Register extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'telegram:register
                            {--z|drop : Drop pending requests}
                            {endpoint? : Set an empty endpoint to disable}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Register the given endpoint to receive telegram webhooks';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dropPending = $this->option('drop');
        $endpoint = is_null($this->argument('endpoint'))
                    ? ''
                    : filter_var($this->argument('endpoint'), FILTER_VALIDATE_URL);

        $this->output->write('Drop pending requests: ');
        $this->output->writeln($dropPending ? '<error>Yes</error>' : '<info>No</info>');

        if ($endpoint === '') {
            $this->line('Disable webhooks');
        } else {
            $this->output->write('New endpoint: ');
            if ($endpoint) {
                $this->info($endpoint);
            } else {
                $this->error('Invalid');
                return 1;
            }
        }

        $this->newLine();

        if (App::isProduction() && !$this->confirm('Do you wish to continue?')) {
            $this->error('Abort by user');
        }

        $response = Http::post(
            config('telegram.endpoint') . '/bot' . config('telegram.token') . '/setWebhook',
            [
                'drop_pending_updates' => $dropPending,
                'url' => $endpoint,
            ]
        );

        $this->info($response['description']);

        return $response->successful() ? 0 : 1;
    }
}
