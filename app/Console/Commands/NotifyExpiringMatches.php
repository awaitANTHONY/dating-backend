<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\UserMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyExpiringMatches extends Command
{
    protected $signature   = 'matches:notify-expiring';
    protected $description = 'Send push notifications to users whose matches expire within 24 hours';

    public function handle(): int
    {
        $freeExpiryHours    = (int) (Setting::where('name', 'match_expiry_hours_free')->value('value')    ?: 48);
        $premiumExpiryHours = (int) (Setting::where('name', 'match_expiry_hours_premium')->value('value') ?: 168);

        $notified = 0;

        // Process free-user matches: expires between (freeExpiry - 25h) and (freeExpiry - 23h)
        $notified += $this->notifyExpiring($freeExpiryHours, false);

        // Process premium matches with a different threshold
        if ($premiumExpiryHours !== $freeExpiryHours) {
            $notified += $this->notifyExpiring($premiumExpiryHours, true);
        }

        $this->info("Expiring match notifications sent: {$notified}");
        return 0;
    }

    private function notifyExpiring(int $expiryHours, bool $premiumOnly): int
    {
        // Matches that will expire in ~24 hours: updated_at is between (expiry-25h) and (expiry-23h) ago
        $windowStart = now()->subHours($expiryHours - 23); // updated_at < this
        $windowEnd   = now()->subHours($expiryHours - 25); // updated_at > this

        $matches = UserMatch::whereBetween('updated_at', [$windowEnd, $windowStart])
            ->with(['user', 'targetUser'])
            ->get();

        $count = 0;

        foreach ($matches as $match) {
            foreach (['user', 'targetUser'] as $side) {
                $person = $match->$side;
                if (!$person) continue;

                $isPremium = (bool) $person->isVipActive();

                // Only notify premium users for the premium threshold batch (and vice versa)
                if ($premiumOnly && !$isPremium) continue;
                if (!$premiumOnly && $isPremium) continue;

                if (!$person->device_token) continue;

                $other = $side === 'user' ? $match->targetUser : $match->user;
                $otherName = $other ? ($other->name ?? 'your match') : 'your match';

                try {
                    send_notification('single', '⏰ Match Expiring Soon', "Your match with {$otherName} expires in 24 hours — tap to revive it!", null, [
                        'device_token' => $person->device_token,
                        'type'         => 'match_expiring',
                        'match_id'     => (string) $match->id,
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    Log::warning("NotifyExpiringMatches: failed for user {$person->id}: " . $e->getMessage());
                }
            }
        }

        return $count;
    }
}
