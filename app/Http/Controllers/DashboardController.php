<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\Report;
use Carbon\Carbon;
use DB;

class DashboardController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $now = Carbon::now();

        // User counts
        $totalUsers = User::where('user_type', 'user')->count();
        $activeUsers = User::where('user_type', 'user')->where('status', 1)->count();
        $bannedUsers = User::where('user_type', 'user')->where('status', 4)->count();

        // New users (last 7 days and last 30 days)
        $newUsersWeek = User::where('user_type', 'user')
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->count();
        $newUsersMonth = User::where('user_type', 'user')
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->count();

        // Active in last 24h (users who had activity)
        $activeToday = User::where('user_type', 'user')
            ->where('last_activity', '>=', $now->copy()->subDay())
            ->count();

        // Subscribers
        $totalSubscribers = User::where('user_type', 'user')
            ->where('subscription_id', '>', 0)
            ->where(function ($q) use ($now) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>', $now);
            })
            ->count();

        // VIP users
        $vipUsers = User::where('user_type', 'user')
            ->where('is_vip', true)
            ->where('vip_expire', '>', $now)
            ->count();

        // Verification stats
        $pendingVerifications = VerificationRequest::pending()->count();
        $totalVerified = DB::table('user_information')->where('is_verified', true)->count();

        // Reports
        $pendingReports = Report::pending()->count();

        // Gender breakdown
        $genderStats = DB::table('user_information')
            ->select('gender', DB::raw('COUNT(*) as count'))
            ->whereNotNull('gender')
            ->groupBy('gender')
            ->pluck('count', 'gender')
            ->toArray();

        // Top countries
        $topCountries = DB::table('user_information')
            ->select('country_code', DB::raw('COUNT(*) as count'))
            ->whereNotNull('country_code')
            ->where('country_code', '!=', '')
            ->groupBy('country_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // New users per day (last 14 days) for chart
        $dailySignups = User::where('user_type', 'user')
            ->where('created_at', '>=', $now->copy()->subDays(13)->startOfDay())
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Fill in missing days with 0
        $chartLabels = [];
        $chartData = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->format('Y-m-d');
            $chartLabels[] = Carbon::parse($date)->format('M d');
            $chartData[] = $dailySignups[$date] ?? 0;
        }

        return view('backend.dashboard', compact(
            'totalUsers', 'activeUsers', 'bannedUsers',
            'newUsersWeek', 'newUsersMonth', 'activeToday',
            'totalSubscribers', 'vipUsers',
            'pendingVerifications', 'totalVerified',
            'pendingReports',
            'genderStats', 'topCountries',
            'chartLabels', 'chartData'
        ));
    }
}
