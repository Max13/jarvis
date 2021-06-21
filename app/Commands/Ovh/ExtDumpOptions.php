<?php

namespace App\Commands\Ovh;

use App\Models\Ovh\Extension;
use DOMDocument;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use SimpleXMLElement;

class ExtDumpOptions extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'ovh:ext:options
                            {--b|batch=10 : How many at a time to fetch}
                            {--older-than=7 : Do not fetch options updated less than given days ago}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Dump OVH entensions options from HTML';

    /**
     * Transform raw extension data, got by parsing html
     *
     * @param  array $data
     * @return array
     */
    protected function transformRawData(array $data)
    {
        $newData = [];

        foreach ($data as $key => $val) {
            if ($key === 'Booking period') {
                if (Str::contains($val, ' to ')) {
                    $periods = explode(' ', $val);
                    $newData['register'] = [
                        'min' => $periods[0],
                        'max' => $periods[2],
                    ];
                } else {
                    $period = intval($val);
                    $newData['register'] = [
                        'min' => $period,
                        'max' => $period,
                    ];
                }
            }

            if ($key === 'Renewal period') {
                if (Str::contains($val, ' to ')) {
                    $periods = explode(' ', $val);
                    $newData['renewal'] = [
                        'min' => $periods[0],
                        'max' => $periods[2],
                    ];
                } else {
                    $period = intval($val);
                    $newData['renewal'] = [
                        'min' => $period,
                        'max' => $period,
                    ];
                }
            }

            if ($key === 'Delivery time') {
                continue;
            }

            if ($key === 'Domain size') {
                $sizes = explode(' ', $val);
                $newData['size'] = [
                    'min' => $sizes[0],
                    'max' => $sizes[2],
                ];
            }

            if ($key === 'WHOIS link') {
                $newData['whois'] = $val;
            }

            if ($key === 'Internationalised domain names (IDN) supported') {
                $newData['idn'] = $val;
            }

            if ($key === 'Change owner price') {
                $newData['change_owner_price'] = $val;
            }

            if ($key === 'Redemption period') {
                $newData['redemption'] = $val;
            }

            if ($key === 'Restore price') {
                $newData['restore_price'] = $val;
            }
        }

        return $newData;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $batch = intval($this->option('batch'));
        $olderThan = now()->subDay($this->option('older-than'));
        $extensions = Extension::latestUpdate()
                               ->whereNotNull('options->url')
                               ->where('updated_at', '<', $olderThan->toDateTimeString())
                               ->oldest()
                               ->limit($batch)
                               ->get();

        $extensions->each(function ($ext) {
            $this->output->write("<info>- $ext->tld</info>: ");

            $domdoc = new DOMDocument;
            throw_unless(
                $domdoc->loadHTML(Http::get($ext->options['url'])->body(), LIBXML_NOBLANKS | LIBXML_NOERROR),
                'RuntimeException',
                'HTML couldn’t be loaded'
            );

            $sxml = simplexml_import_dom($domdoc);
            throw_unless($sxml, 'RuntimeException', 'DOM couldn’t be loaded');

            $extData = [];

            $creation_data = $sxml->xpath('//div[@id="creation_content"]//table/tr');
            if (count($creation_data) === 0) {
                return true;
            }
            foreach ($creation_data as $line) {
                $key = trim(preg_replace('/\s+/', ' ', $line->th), " \n:");

                if ($key === 'Internationalised domain names (IDN) supported') {
                    $val = strtolower($line->td->span) === 'yes';
                } elseif ($key === 'WHOIS link') {
                    if ($href = $line->td->a->attributes()->href) {
                        $val = strval($href);
                    } else {
                        $val = strval($line->td->a);
                    }
                } elseif ($key === 'Price') {
                    continue;
                } else {
                    $val = rtrim(preg_replace('/\s+/', ' ', $line->td));
                }

                $extData[$key] = $val;
            }

            $trade_data = $sxml->xpath('//div[@id="trade_content"]//table/tr');
            if (count($trade_data) === 0) {
                return true;
            }
            foreach ($trade_data as $line) {
                $key = trim(preg_replace('/\s+/', ' ', $line->th), " \n:");

                if ($key === 'Price') {
                    $key = 'Change owner price';
                    $val = trim(strip_tags($line->td->asXML()));
                    $val = $val === 'Free' ? 0 : floatval(substr($val, 3));
                } else {
                    $val = trim(preg_replace('/\s+/', ' ', $line->td));
                }

                $extData[$key] = $val;
            }

            $restore_data = $sxml->xpath('//div[@id="restore_content"]//table/tr');
            if (count($restore_data) === 0) {
                return true;
            }
            foreach ($restore_data as $line) {
                $key = trim(preg_replace('/\s+/', ' ', $line->th), " \n:");

                if ($key === 'Price') {
                    $key = 'Restore price';
                    $val = trim(strip_tags($line->td->asXML()));
                    $val = $val === 'Free' ? 0 : floatval(substr($val, 3));
                } elseif ($key === 'Redemption period') {
                    $val = intval($line->td);
                } elseif ($key === 'Reactivation by the owner') {
                    continue;
                } else {
                    $val = trim(preg_replace('/\s+/', ' ', $line->td));
                }

                $extData[$key] = $val;
            }

            $ext->options = $this->transformRawData($extData);

            $ext->updated_at = now();
            if ($ext->save()) {
                $this->line('OK');
            } else {
                $this->error('KO');
            }
        });
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        $schedule->command(static::class)->everyThirtyMinutes();
    }
}
