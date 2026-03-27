<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['title' => 'French',     'status' => 1],
            ['title' => 'German',     'status' => 1],
            ['title' => 'Polish',     'status' => 1],
            ['title' => 'Portuguese', 'status' => 1],
            ['title' => 'Russian',    'status' => 1],
            ['title' => 'Spanish',    'status' => 1],
            ['title' => 'Ukrainian',  'status' => 1],
        ];

        foreach ($languages as $lang) {
            Language::firstOrCreate(
                ['title' => $lang['title']],
                ['image' => '', 'status' => $lang['status']]
            );
        }

        Cache::forget('languages');
    }
}
