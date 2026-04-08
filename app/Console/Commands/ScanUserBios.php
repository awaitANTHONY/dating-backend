<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ScanUserBios extends Command
{
    protected $signature = 'bios:scan {--fix : Clear flagged bios instead of just reporting}';

    protected $description = 'Scan existing user bios for hidden contact information (phone numbers, social media, etc.)';

    public function handle(): int
    {
        $this->info('Scanning user bios for hidden contact information...');

        $flagged = [];

        User::whereHas('user_information', function ($q) {
            $q->whereNotNull('bio')->where('bio', '!=', '');
        })->with('user_information')->chunk(500, function ($users) use (&$flagged) {
            foreach ($users as $user) {
                $bio = $user->user_information->bio ?? '';
                if (empty($bio)) continue;

                $result = $this->checkContent($bio);
                if (!$result['valid']) {
                    $flagged[] = [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email ?? $user->phone,
                        'bio' => mb_substr($bio, 0, 80),
                        'reason' => $result['message'],
                    ];
                }
            }
        });

        if (empty($flagged)) {
            $this->info('No flagged bios found.');
            return 0;
        }

        $this->warn(count($flagged) . ' flagged bio(s) found:');
        $this->newLine();

        $this->table(
            ['User ID', 'Name', 'Email/Phone', 'Bio (truncated)', 'Reason'],
            $flagged
        );

        if ($this->option('fix')) {
            $ids = array_column($flagged, 'id');
            $count = \App\Models\UserInformation::whereIn('user_id', $ids)
                ->update(['bio' => null]);
            $this->info("Cleared {$count} flagged bios.");
        } else {
            $this->info('Run with --fix to clear these bios.');
        }

        return 0;
    }

    private function checkContent(string $content): array
    {
        $lowerContent = strtolower($content);

        // Standard phone patterns
        $phonePatterns = [
            '/\d{10,}/',
            '/\d{3}[-.\s]\d{3}[-.\s]\d{4}/',
            '/\(\d{3}\)\s*\d{3}[-.\s]\d{4}/',
            '/\+\d{1,3}\s*\d{9,}/',
            '/\d{3}\s\d{3}\s\d{4}/',
            '/0\d{2,3}[-.\s]\d{3,4}[-.\s]\d{3,4}/',
        ];

        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return ['valid' => false, 'message' => 'Phone number pattern'];
            }
        }

        // Obfuscated phone: digit groups hidden in text
        preg_match_all('/\d{3,}/', $content, $matches);
        $digitGroups = $matches[0] ?? [];

        if (count($digitGroups) >= 2) {
            $phoneGroups = array_values(array_filter($digitGroups, function ($g) {
                if (strlen($g) == 4) {
                    $num = intval($g);
                    return !($num >= 1950 && $num <= 2030);
                }
                return true;
            }));

            $combined = implode('', $phoneGroups);
            if (count($phoneGroups) >= 2 && strlen($combined) >= 7) {
                return ['valid' => false, 'message' => 'Hidden phone number (digit groups)'];
            }
        }

        // Scattered single digits
        preg_match_all('/(?<!\d)\d(?!\d)/', $content, $singleDigits);
        if (count($singleDigits[0] ?? []) >= 7) {
            return ['valid' => false, 'message' => 'Scattered digits'];
        }

        // Number words
        $numberWords = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
        $numberWordCount = 0;
        foreach ($numberWords as $word) {
            $numberWordCount += preg_match_all('/\b' . $word . '\b/', $lowerContent);
        }
        if ($numberWordCount >= 7) {
            return ['valid' => false, 'message' => 'Number words'];
        }

        // Contact keywords
        $contactKeywords = [
            'whatsapp', 'whats app', 'whatsap', 'watsapp', 'watsap', 'wa number', 'wa me',
            'telegram', 'telegrm', 'tg number', 't.me',
            'snapchat', 'snap chat', 'snap me', 'snap:',
            'instagram', 'insta', 'ig:', 'dm me', 'dm on',
            'facebook', 'fb', 'messenger',
            'wechat', 'we chat', 'line app', 'line:',
            'viber', 'skype', 'kik',
            'call me', 'text me', 'phone me', 'ring me',
            'my number', 'my phone', 'reach me at',
            '@gmail', '@yahoo', '@hotmail', '@outlook', 'email me', 'e-mail',
            'contact me', 'add me',
        ];

        foreach ($contactKeywords as $keyword) {
            if (strpos($lowerContent, $keyword) !== false) {
                return ['valid' => false, 'message' => "Keyword: {$keyword}"];
            }
        }

        // Social media handles
        if (preg_match('/@[a-zA-Z0-9_]{3,}/', $content)) {
            return ['valid' => false, 'message' => 'Social media handle'];
        }

        // URLs
        if (preg_match('/https?:\/\/|www\.|\.com|\.net|\.org|\.io/i', $content)) {
            return ['valid' => false, 'message' => 'URL detected'];
        }

        return ['valid' => true, 'message' => ''];
    }
}
