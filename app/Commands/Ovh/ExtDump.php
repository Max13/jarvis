<?php

namespace App\Commands\Ovh;

use App\Models\Ovh\Extension;
use DOMDocument;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

class ExtDump extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'ovh:ext:dump
                            {--o|out= : Output dump to a given file (or "stdout")}
                            {--no-fetch : Don’t fetch from OVH, just dump}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Dump OVH entensions prices from HTML';

    /**
     * Base URL
     *
     * @var string
     */
    protected $baseUrl = 'https://www.ovh.ie';

    /**
     * Prices page URI
     *
     * @var string
     */
    protected $pricesUri = '/domains/prices';

    /**
     * Extensions retrieved
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!$this->option('no-fetch')) {
            $html = Cache::remember('ovh:ext:html', now()->addHours(10), function () {
                return Http::get($this->baseUrl . $this->pricesUri)->body();
            });

            $domdoc = new DOMDocument;
            throw_unless(
                $domdoc->loadHTML($html, LIBXML_NOBLANKS | LIBXML_NOERROR),
                'RuntimeException',
                'HTML couldn’t be loaded'
            );

            $sxml = simplexml_import_dom($domdoc);
            throw_unless($sxml, 'RuntimeException', 'DOM couldn’t be loaded');

            $data = $sxml->xpath('//table[@id="dataTable"]/tbody/tr');

            DB::transaction(function () use ($data) {
                if ($this->option('out') !== 'stdout') {
                    $bar = $this->output->createProgressBar(count($data));
                } else {
                    $bar = optional();
                }
                $bar->start();

                foreach ($data as $ext) {
                    $tld = strval($ext->attributes()->{'data-ext'});
                    $options = [];
                    if ($url = strval($ext->td[0]->a->attributes()->{'href'})) {
                        $options['url'] = $this->baseUrl . $url;
                    }
                    $register = strval($ext->td[1]->attributes()->{'data-order'}) ?? null;
                    $renew = strval($ext->td[2]->attributes()->{'data-order'}) ?? null;
                    $transfer = strval($ext->td[3]->attributes()->{'data-order'}) ?? null;

                    $extension = Extension::where('tld', $tld)
                                          ->latest()
                                          ->firstOrNew()
                                          ->fill([
                                              'tld' => $tld,
                                              'register' => $register,
                                              'renew' => $renew,
                                              'transfer' => $transfer,
                                          ]);

                    if (count($extension->options) === 0) {
                        $extension->options = $options;
                    }

                    if ($extension->isDirty(['register', 'renew', 'transfer'])) {
                        $extension = $extension->replicate();
                    }

                    $extension->updated_at = now();
                    $extension->save();
                    $bar->advance();
                }

                $bar->finish();
            });
        }

        if (($dst = $this->option('out'))) {
            $extensions = Extension::latestUpdate()->get();

            if ($dst === 'stdout') {
                $this->line($extensions->toJson());
            } else {
                file_put_contents($dst, $extensions->toJson());
            }
        }
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        $schedule->command(static::class)->dailyAt('08:00');
    }
}
