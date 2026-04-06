<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VerificationRequest;
use App\Models\User;
use DataTables;
use Carbon\Carbon;

class VerificationRequestController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = VerificationRequest::with('user')
                ->orderBy('created_at', 'DESC');

            // Status filter
            if ($request->filled('filter_status')) {
                $query->where('status', $request->filter_status);
            }

            return DataTables::of($query)
                ->addColumn('user_name', function ($vr) {
                    return optional($vr->user)->name ?? '-';
                })
                ->addColumn('user_image', function ($vr) {
                    $img = optional($vr->user)->image;
                    if ($img) {
                        return '<img class="img-sm img-thumbnail" src="' . asset($img) . '">';
                    }
                    return '-';
                })
                ->editColumn('image', function ($vr) {
                    if ($vr->image) {
                        return '<a href="' . asset($vr->image) . '" target="_blank"><img src="' . asset($vr->image) . '" style="width:60px;height:60px;object-fit:cover;border-radius:4px;"></a>';
                    }
                    return '-';
                })
                ->editColumn('status', function ($vr) {
                    if ($vr->status === 'approved') {
                        return '<span class="badge badge-success">Approved</span>';
                    } elseif ($vr->status === 'rejected') {
                        return '<span class="badge badge-danger">Rejected</span>';
                    }
                    return '<span class="badge badge-warning">Pending</span>';
                })
                ->editColumn('created_at', function ($vr) {
                    return $vr->created_at ? $vr->created_at->format('M d, Y H:i') : '-';
                })
                ->addColumn('action', function ($vr) {
                    $action = '<div class="dropdown">';
                    $action .= '<button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action') . '</button>';
                    $action .= '<div class="dropdown-menu">';
                    $action .= '<a href="' . url('verification-requests/' . $vr->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Review Verification') . '"><i class="fas fa-eye"></i> ' . _lang('Review') . '</a>';
                    if ($vr->status === 'pending') {
                        $action .= '<a href="' . url('verification-requests/' . $vr->id . '/approve') . '" class="dropdown-item ajax-get-confirm" data-confirm="' . _lang('Approve this verification?') . '"><i class="fas fa-check text-success"></i> ' . _lang('Approve') . '</a>';
                        $action .= '<a href="' . url('verification-requests/' . $vr->id . '/reject') . '" class="dropdown-item ajax-modal" data-title="' . _lang('Reject Verification') . '"><i class="fas fa-times text-danger"></i> ' . _lang('Reject') . '</a>';
                    }
                    $action .= '</div></div>';
                    return $action;
                })
                ->rawColumns(['action', 'status', 'image', 'user_image'])
                ->make(true);
        }

        return view('backend.verification_requests.index');
    }

    public function show(Request $request, $id)
    {
        $vr = VerificationRequest::with('user', 'user.user_information')->findOrFail($id);

        if ($request->ajax()) {
            return view('backend.verification_requests.modal.show', compact('vr'));
        }
        return view('backend.verification_requests.show', compact('vr'));
    }

    public function approve(Request $request, $id)
    {
        $vr = VerificationRequest::findOrFail($id);
        $vr->status = 'approved';
        $vr->save();

        // Update user verification status
        $user = User::find($vr->user_id);
        if ($user) {
            $user->verification_status = 'approved';
            $user->verified_at = Carbon::now();
            $user->save();

            // Also mark user_information as verified
            if ($user->user_information) {
                $user->user_information->is_verified = true;
                $user->user_information->save();
            }
        }

        if ($request->ajax()) {
            return response()->json(['result' => 'success', 'message' => _lang('Verification approved successfully')]);
        }
        return back()->with('success', _lang('Verification approved successfully'));
    }

    public function reject(Request $request, $id)
    {
        $vr = VerificationRequest::findOrFail($id);
        $vr->status = 'rejected';
        $vr->reason = $request->reason ?? 'Rejected by admin';
        $vr->save();

        // Update user verification status
        $user = User::find($vr->user_id);
        if ($user) {
            $user->verification_status = 'rejected';
            $user->verified_at = null;
            $user->save();
        }

        if ($request->ajax()) {
            return response()->json(['result' => 'success', 'message' => _lang('Verification rejected')]);
        }
        return back()->with('success', _lang('Verification rejected'));
    }
}
