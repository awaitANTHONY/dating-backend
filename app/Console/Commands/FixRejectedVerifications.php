<?php

namespace App\Console\Commands;

use App\Models\VerificationRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixRejectedVerifications extends Command
{
    protected $signature = 'verifications:fix-rejected';

    protected $description = 'Bulk approve verification requests where AI said approved but were wrongly rejected due to confidence routing bug';

    public function handle(): int
    {
        $this->info('Scanning for wrongly rejected verifications...');

        $wronglyRejected = VerificationRequest::where('status', 'rejected')
            ->whereNotNull('ai_response')
            ->get()
            ->filter(function ($vr) {
                $aiResponse = is_string($vr->ai_response)
                    ? json_decode($vr->ai_response, true)
                    : $vr->ai_response;

                return ($aiResponse['status'] ?? '') === 'approved';
            });

        if ($wronglyRejected->isEmpty()) {
            $this->info('No wrongly rejected verifications found.');
            return 0;
        }

        $this->warn($wronglyRejected->count() . ' wrongly rejected verification(s) found:');
        $this->newLine();

        $rows = [];
        foreach ($wronglyRejected as $vr) {
            $aiResponse = is_string($vr->ai_response)
                ? json_decode($vr->ai_response, true)
                : $vr->ai_response;

            $rows[] = [
                $vr->id,
                $vr->user_id,
                $vr->user->name ?? 'N/A',
                $aiResponse['confidence'] ?? 'N/A',
                $vr->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table(['Request ID', 'User ID', 'Name', 'AI Confidence', 'Submitted'], $rows);

        $approved = 0;
        foreach ($wronglyRejected as $vr) {
            $vr->status = 'approved';
            $vr->save();

            $user = User::find($vr->user_id);
            if ($user) {
                $user->verification_status = 'approved';
                $user->verified_at = Carbon::now();
                $user->verification_attempts = 0;
                $user->verification_cooldown_until = null;
                $user->save();

                if ($user->user_information) {
                    $user->user_information->is_verified = true;
                    $user->user_information->save();
                }
            }

            $approved++;
        }

        $this->info("Approved {$approved} verification(s).");
        return 0;
    }
}
