<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /**
     * Submit a report against a user.
     *
     * POST /api/v1/reports
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reported_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason'           => ['required', 'string', 'max:500'],
        ]);

        $reporterId     = $request->user()->id;
        $reportedUserId = (int) $validated['reported_user_id'];

        // Prevent self-reporting
        if ($reporterId === $reportedUserId) {
            return response()->json([
                'status'  => false,
                'message' => 'You cannot report yourself',
            ], 400);
        }

        // Prevent duplicate pending report from same reporter on same user
        $existing = Report::where('reporter_id', $reporterId)
            ->where('reported_user_id', $reportedUserId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'status'  => false,
                'message' => 'You have already submitted a pending report for this user',
            ], 409);
        }

        Report::create([
            'reporter_id'      => $reporterId,
            'reported_user_id' => $reportedUserId,
            'reason'           => $validated['reason'],
            'status'           => 'pending',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Report submitted successfully',
        ]);
    }

    /**
     * Get the current user's submitted reports.
     *
     * GET /api/v1/reports
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $reports = Report::where('reporter_id', $request->user()->id)
            ->with('reportedUser:id,name,email,image')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'data'   => $reports,
        ]);
    }
}
