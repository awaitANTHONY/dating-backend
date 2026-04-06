<?php

namespace App\Console\Commands;

use App\Models\UserInformation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BackfillCountryCodes extends Command
{
    protected $signature = 'users:backfill-country-codes {--chunk=100} {--sleep=1}';
    protected $description = 'Backfill country_code from latitude/longitude for users missing it';

    public function handle()
    {
        $chunkSize = (int) $this->option('chunk');
        $sleepSeconds = (int) $this->option('sleep');

        $total = UserInformation::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($q) {
                $q->whereNull('country_code')->orWhere('country_code', '');
            })
            ->count();

        $this->info("Found {$total} users with coordinates but no country code.");

        if ($total === 0) {
            $this->info('Nothing to do.');
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $failed = 0;

        UserInformation::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($q) {
                $q->whereNull('country_code')->orWhere('country_code', '');
            })
            ->chunkById($chunkSize, function ($users) use (&$updated, &$failed, $bar, $sleepSeconds) {
                foreach ($users as $info) {
                    try {
                        $response = Http::timeout(10)
                            ->withHeaders([
                                'User-Agent' => 'InMessage-Backfill/1.0',
                            ])
                            ->get('https://nominatim.openstreetmap.org/reverse', [
                                'format' => 'json',
                                'lat' => $info->latitude,
                                'lon' => $info->longitude,
                                'zoom' => 3,
                            ]);

                        if ($response->successful()) {
                            $data = $response->json();
                            $code = $data['address']['country_code'] ?? null;

                            if ($code && strlen($code) === 2) {
                                $info->country_code = strtoupper($code);
                                $info->save();
                                $updated++;
                            } else {
                                $failed++;
                            }
                        } else {
                            $failed++;
                        }
                    } catch (\Exception $e) {
                        $failed++;
                    }

                    $bar->advance();

                    // Nominatim requires max 1 request per second
                    sleep($sleepSeconds);
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done! Updated: {$updated}, Failed: {$failed}");

        return 0;
    }
}
