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
                    } elseif ($vr->status === 'pending_admin_review') {
                        return '<span class="badge badge-info">Needs Review</span>';
                    }
                    return '<span class="badge badge-warning">Pending</span>';
                })
                ->editColumn('created_at', function ($vr) {
                    return $vr->created_at ? $vr->created_at->format('M d, Y H:i') : '-';
                })
                ->addColumn('action', function ($vr) {
                    $reviewUrl = url('verification-requests/' . $vr->id);
                    $buttons = '<a href="' . $reviewUrl . '" class="btn btn-info btn-sm ajax-modal mb-1" data-title="Verification Review"><i class="fas fa-eye"></i> Review</a>';
                    if (in_array($vr->status, ['pending', 'rejected', 'pending_admin_review'])) {
                        $buttons .= ' <button class="btn btn-success btn-sm btn-quick-approve mb-1" data-id="' . $vr->id . '" title="Approve"><i class="fas fa-check"></i></button>';
                    }
                    if (in_array($vr->status, ['pending', 'pending_admin_review'])) {
                        $buttons .= ' <button class="btn btn-danger btn-sm btn-quick-reject mb-1" data-id="' . $vr->id . '" title="Reject"><i class="fas fa-times"></i></button>';
                    }
                    // Ban/Unban button
                    $userId = optional($vr->user)->id;
                    $userStatus = optional($vr->user)->status;
                    if ($userId) {
                        if ($userStatus == 4) {
                            $buttons .= ' <a href="' . url('users/' . $userId . '/unban') . '" class="btn btn-warning btn-sm ajax-get-confirm mb-1" data-confirm="Unban this user?" title="Unban"><i class="fas fa-unlock"></i></a>';
                        } else {
                            $buttons .= ' <a href="' . url('users/' . $userId . '/ban') . '" class="btn btn-dark btn-sm ajax-get-confirm mb-1" data-confirm="Ban this user permanently?" title="Ban"><i class="fas fa-ban"></i></a>';
                        }
                    }
                    return $buttons;
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
            $user->verification_attempts = 0;
            $user->verification_cooldown_until = null;
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
