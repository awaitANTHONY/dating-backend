<?php

namespace App\Console\Commands;

use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireContactRequests extends Command
{
    protected $signature = 'direct-connect:expire';
    protected $description = 'Expire pending contact requests older than 7 days';

    public function handle()
    {
        $expiredRequests = ContactRequest::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->get();

        $count = $expiredRequests->count();

        foreach ($expiredRequests as $request) {
            $request->status = 'expired';
            $request->save();

            // Notify requester (no refund)
            try {
                $requester = User::find($request->requester_id);
                if ($requester && $requester->device_token) {
                    send_notification(
                        'single',
                        'Request Expired',
                        'Your connect request has expired.',
                        null,
                        [
                            'device_token' => $requester->device_token,
                            'type' => 'direct_connect_expired',
                            'request_id' => (string) $request->id,
                        ]
                    );
                }
            } catch (\Exception $e) {
                Log::error('DirectConnect expire notification failed', ['error' => $e->getMessage()]);
            }
        }

        Log::info("DirectConnect: Expired {$count} pending requests");
        $this->info("Expired {$count} pending contact requests.");

        return Command::SUCCESS;
    }
}
