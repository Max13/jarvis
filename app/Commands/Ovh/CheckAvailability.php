<?php

namespace App\Commands\Ovh;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

class CheckAvailability extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'ovh:check:availability {hardware}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Check server availability at OVH';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $availabilities = Http::get('https://www.ovh.com/engine/api/dedicated/server/availabilities', [
            'country' => 'fr',
            'hardware' => $this->argument('hardware'),
        ]);

        if (!$availabilities->ok()) {
            $this->error("Couldn't request availabilities");
            return;
        }

        $result = false;
        $table = [];
        foreach ($availabilities->json() as $region) {
            foreach ($region['datacenters'] as $dc) {
                $table[] = [
                    'DC' => $dc['datacenter'],
                    'Availability' => $dc['availability'],
                ];

                if ($dc['availability'] != 'unavailable') {
                    $result = true;
                }
            }
        }

        $this->table(['DC', 'Availability'], $table);

        return !$result;
    }
}
