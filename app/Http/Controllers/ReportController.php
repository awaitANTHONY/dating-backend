<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\User;
use DataTables;
use Validator;

class ReportController extends Controller
{
    /**
     * Display a listing of all reports (Admin Dashboard).
     */
    public function index(Request $request)
    {
        $reports = Report::with(['reporter:id,name,email', 'reportedUser:id,name,email'])
            ->orderBy('id', 'DESC');

        // Optional status filter
        if ($request->has('status') && in_array($request->status, ['pending', 'reviewed', 'dismissed'])) {
            $reports->where('status', $request->status);
        }

        if ($request->ajax()) {
            return DataTables::of($reports)
                ->addColumn('reporter_name', function ($report) {
                    return $report->reporter ? $report->reporter->name : 'Deleted User';
                })
                ->addColumn('reported_user_name', function ($report) {
                    return $report->reportedUser ? $report->reportedUser->name : 'Deleted User';
                })
                ->addColumn('report_count', function ($report) {
                    return Report::where('reported_user_id', $report->reported_user_id)->count();
                })
                ->editColumn('status', function ($report) {
                    $badges = [
                        'pending'   => 'warning',
                        'reviewed'  => 'success',
                        'dismissed' => 'secondary',
                    ];
                    $badge = $badges[$report->status] ?? 'info';
                    return '<span class="badge badge-' . $badge . '">' . ucfirst($report->status) . '</span>';
                })
                ->editColumn('created_at', function ($report) {
                    return $report->created_at->format('Y-m-d H:i');
                })
                ->addColumn('action', function ($report) {
                    $action = '<div class="dropdown">
                                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        ' . _lang('Action') . '
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                    $action .= '<a href="' . route('reports.show', $report->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Report Details') . '">
                                    <i class="fas fa-eye"></i>
                                    ' . _lang('View') . '
                                </a>';
                    if ($report->status === 'pending') {
                        $action .= '<a href="' . route('reports.update-status', [$report->id, 'reviewed']) . '" class="dropdown-item">
                                        <i class="fas fa-check"></i>
                                        ' . _lang('Mark Reviewed') . '
                                    </a>';
                        $action .= '<a href="' . route('reports.update-status', [$report->id, 'dismissed']) . '" class="dropdown-item">
                                        <i class="fas fa-times"></i>
                                        ' . _lang('Dismiss') . '
                                    </a>';
                    }
                    $action .= '<form action="' . route('reports.destroy', $report->id) . '" method="post" class="ajax-delete">'
                                . csrf_field()
                                . method_field('DELETE')
                                . '<button type="button" class="btn-remove dropdown-item">
                                        <i class="fas fa-trash-alt"></i>
                                        ' . _lang('Delete') . '
                                    </button>
                                </form>';
                    $action .= '</div></div>';
                    return $action;
                })
                ->rawColumns(['action', 'status'])
                ->make(true);
        }

        return view('backend.reports.index');
    }

    /**
     * Display the specified report details.
     */
    public function show(Request $request, $id)
    {
        $report = Report::with(['reporter:id,name,email', 'reportedUser:id,name,email'])->findOrFail($id);

        // Get all reports against the same reported user
        $allReports = Report::where('reported_user_id', $report->reported_user_id)
            ->with('reporter:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        if (!$request->ajax()) {
            return view('backend.reports.show', compact('report', 'allReports'));
        } else {
            return view('backend.reports.modal.show', compact('report', 'allReports'));
        }
    }

    /**
     * Update report status (reviewed / dismissed).
     */
    public function updateStatus($id, $status)
    {
        if (!in_array($status, ['reviewed', 'dismissed'])) {
            return back()->with('error', _lang('Invalid status.'));
        }

        $report = Report::findOrFail($id);
        $report->status = $status;
        $report->save();

        cache()->flush();

        return back()->with('success', _lang('Report marked as ' . $status . '.'));
    }

    /**
     * Remove the specified report.
     */
    public function destroy(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        $report->delete();

        cache()->flush();

        if (!$request->ajax()) {
            return redirect('reports')->with('success', _lang('Report has been deleted successfully.'));
        } else {
            return response()->json(['result' => 'success', 'message' => _lang('Report has been deleted successfully.')]);
        }
    }

    /**
     * Bulk update status for multiple reports.
     */
    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'report_ids' => 'required|array',
            'report_ids.*' => 'integer|exists:reports,id',
            'status' => 'required|in:reviewed,dismissed',
        ]);

        Report::whereIn('id', $validated['report_ids'])
            ->update(['status' => $validated['status']]);

        cache()->flush();

        if (!$request->ajax()) {
            return back()->with('success', _lang('Reports updated successfully.'));
        } else {
            return response()->json(['result' => 'success', 'message' => _lang('Reports updated successfully.')]);
        }
    }
}
