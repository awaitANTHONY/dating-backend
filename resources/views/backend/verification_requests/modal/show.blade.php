@php
    $user     = optional($vr->user);
    $userInfo = optional($user->user_information);

    // Collect all profile photos
    $profilePhotos = [];
    if ($user->image) {
        $profilePhotos[] = $user->image;
    }
    $galleryImages = $userInfo->images ?? null;
    if (is_string($galleryImages)) {
        $galleryImages = json_decode($galleryImages, true);
    }
    if (is_array($galleryImages)) {
        foreach ($galleryImages as $img) {
            if ($img && !in_array($img, $profilePhotos)) {
                $profilePhotos[] = $img;
            }
        }
    }
@endphp

{{-- ── TOP: verification selfie + comparison ── --}}
<div class="row mb-3">
    <div class="col-md-4 text-center">
        <p class="text-muted mb-1" style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Verification Selfie</p>
        @if($vr->image)
            <a href="{{ asset($vr->image) }}" target="_blank">
                <img src="{{ asset($vr->image) }}"
                     class="img-thumbnail rounded"
                     style="width:140px;height:170px;object-fit:cover;">
            </a>
        @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:140px;height:170px;margin:auto;">
                <span class="text-muted small">No selfie</span>
            </div>
        @endif
    </div>
    <div class="col-md-8">
        <p class="text-muted mb-1" style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Profile Photos</p>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            @forelse($profilePhotos as $photo)
                <a href="{{ asset($photo) }}" target="_blank">
                    <img src="{{ asset($photo) }}"
                         class="img-thumbnail rounded"
                         style="width:80px;height:80px;object-fit:cover;"
                         title="Click to view full size">
                </a>
            @empty
                <span class="text-muted small">No profile photos</span>
            @endforelse
        </div>
    </div>
</div>

<hr class="my-2">

{{-- ── PROFILE INFO ── --}}
<div class="row">
    <div class="col-md-6">
        <table class="table table-sm table-borderless mb-0">
            <tr>
                <td class="text-muted" style="width:110px;">Name</td>
                <td><strong>{{ $user->name ?? '-' }}</strong></td>
            </tr>
            <tr>
                <td class="text-muted">Email</td>
                <td>{{ $user->email ?? '-' }}</td>
            </tr>
            <tr>
                <td class="text-muted">Gender</td>
                <td>{{ ucfirst($userInfo->gender ?? '-') }}</td>
            </tr>
            <tr>
                <td class="text-muted">Age</td>
                <td>
                    @php
                        $dob = $userInfo->dob ?? null;
                        echo $dob ? \Carbon\Carbon::parse($dob)->age . ' yrs (' . $dob . ')' : '-';
                    @endphp
                </td>
            </tr>
            <tr>
                <td class="text-muted">Country</td>
                <td>{{ $userInfo->country_code ?? '-' }}</td>
            </tr>
            <tr>
                <td class="text-muted">Height</td>
                <td>{{ $userInfo->height ?? '-' }}</td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-sm table-borderless mb-0">
            <tr>
                <td class="text-muted" style="width:110px;">Status</td>
                <td>
                    @if($vr->status === 'approved')
                        <span class="badge badge-success">Approved</span>
                    @elseif($vr->status === 'rejected')
                        <span class="badge badge-danger">Rejected</span>
                    @else
                        <span class="badge badge-warning">Pending</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="text-muted">Submitted</td>
                <td>{{ $vr->created_at ? $vr->created_at->format('M d, Y H:i') : '-' }}</td>
            </tr>
            <tr>
                <td class="text-muted">Education</td>
                <td>{{ $userInfo->education ?? '-' }}</td>
            </tr>
            <tr>
                <td class="text-muted">Occupation</td>
                <td>{{ $userInfo->occupation ?? '-' }}</td>
            </tr>
            @if($vr->reason)
            <tr>
                <td class="text-muted">Reason</td>
                <td>{{ $vr->reason }}</td>
            </tr>
            @endif
        </table>
    </div>
</div>

{{-- Bio --}}
@if($userInfo->bio)
<div class="mt-2 p-2 bg-light rounded">
    <small class="text-muted d-block mb-1">Bio</small>
    <p class="mb-0 small">{{ $userInfo->bio }}</p>
</div>
@endif

{{-- Interests --}}
@php
    $interests = $userInfo->interest ?? null;
    if (is_string($interests)) $interests = json_decode($interests, true);
@endphp
@if(!empty($interests))
<div class="mt-2">
    <small class="text-muted">Interests:</small>
    <div class="mt-1">
        @foreach((array)$interests as $tag)
            <span class="badge badge-secondary mr-1">{{ $tag }}</span>
        @endforeach
    </div>
</div>
@endif

{{-- AI Analysis (collapsed) --}}
@if($vr->ai_response)
<div class="mt-2">
    <a data-toggle="collapse" href="#ai-analysis-{{ $vr->id }}" class="text-muted small">
        <i class="fas fa-robot mr-1"></i> View AI Analysis
    </a>
    <div id="ai-analysis-{{ $vr->id }}" class="collapse">
        <div class="bg-light p-2 rounded mt-1" style="max-height:120px;overflow-y:auto;">
            <pre class="mb-0" style="white-space:pre-wrap;font-size:11px;">{{ json_encode($vr->ai_response, JSON_PRETTY_PRINT) }}</pre>
        </div>
    </div>
</div>
@endif

{{-- Approve / Reject actions --}}
@if(in_array($vr->status, ['pending', 'rejected', 'pending_admin_review']))
<hr class="my-3">
<div class="row">
    <div class="col-md-6">
        <a href="{{ url('verification-requests/' . $vr->id . '/approve') }}"
           class="btn btn-success btn-block ajax-get-confirm"
           data-confirm="Approve this verification request?">
            <i class="fas fa-check mr-1"></i> Approve
        </a>
    </div>
    @if($vr->status !== 'rejected')
    <div class="col-md-6">
        <form method="post" class="ajax-submit" action="{{ url('verification-requests/' . $vr->id . '/reject') }}">
            @csrf
            <div class="input-group">
                <input type="text" name="reason" class="form-control form-control-sm"
                       placeholder="Rejection reason (optional)">
                <div class="input-group-append">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fas fa-times mr-1"></i> Reject
                    </button>
                </div>
            </div>
        </form>
    </div>
    @endif
</div>
@endif
