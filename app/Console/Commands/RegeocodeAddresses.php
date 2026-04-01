<?php

namespace App\Console\Commands;

use App\Models\UserInformation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RegeocodeAddresses extends Command
{
    protected $signature = 'users:regeocode-addresses';
    protected $description = 'Re-geocode all user addresses to "Neighborhood, City" format using Nominatim';

    public function handle()
    {
        $users = UserInformation::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', '')
            ->where('longitude', '!=', '')
            ->get();

        $this->info("Found {$users->count()} users with coordinates.");
        $bar = $this->output->createProgressBar($users->count());
        $updated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $lat = $user->latitude;
            $lng = $user->longitude;

            if (!is_numeric($lat) || !is_numeric($lng)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'InMessage/1.0',
                ])->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                    'addressdetails' => 1,
                    'zoom' => 18,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $addr = $data['address'] ?? [];

                    $neighborhood = $this->firstOf($addr, [
                        'neighbourhood', 'suburb', 'quarter',
                        'hamlet', 'isolated_dwelling', 'residential',
                    ]);
                    $city = $this->firstOf($addr, [
                        'city_district', 'city', 'town',
                        'village', 'municipality', 'county', 'district',
                    ]);

                    $parts = [];
                    if (!empty($neighborhood)) {
                        $parts[] = $this->titleCase($neighborhood);
                    }
                    if (!empty($city) && strtolower($city) !== strtolower($neighborhood)) {
                        $parts[] = $this->titleCase($city);
                    }

                    if (empty($parts)) {
                        // Fallback: state, country code
                        $state = $this->firstOf($addr, ['state', 'region', 'province']);
                        $cc = strtoupper($addr['country_code'] ?? '');
                        if (!empty($state)) $parts[] = $this->titleCase($state);
                        if (!empty($cc)) $parts[] = $cc;
                    }

                    $newAddress = implode(', ', $parts);
                    if (!empty($newAddress)) {
                        $user->address = $newAddress;
                        $user->save();
                        $updated++;
                    }
                }

                // Nominatim rate limit: 1 request/second
                usleep(1100000);

            } catch (\Exception $e) {
                $this->warn("Failed for user {$user->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done! Updated: {$updated}, Skipped: {$skipped}");
    }

    private function firstOf(array $addr, array $keys): string
    {
        foreach ($keys as $key) {
            if (!empty($addr[$key])) {
                return $addr[$key];
            }
        }
        return '';
    }

    private function titleCase(string $str): string
    {
        return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    }
}
