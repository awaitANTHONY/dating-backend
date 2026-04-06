<div class="row">
    <div class="col-md-5 text-center">
        <h6>{{ _lang('Profile Photo') }}</h6>
        @if(optional($vr->user)->image)
        <img src="{{ asset($vr->user->image) }}" class="img-thumbnail" style="max-width:200px;max-height:200px;object-fit:cover;">
        @else
        <p class="text-muted">{{ _lang('No profile photo') }}</p>
        @endif
    </div>
    <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
        <i class="fas fa-arrows-alt-h fa-2x text-muted"></i>
    </div>
    <div class="col-md-5 text-center">
        <h6>{{ _lang('Verification Selfie') }}</h6>
        @if($vr->image)
        <a href="{{ asset($vr->image) }}" target="_blank">
            <img src="{{ asset($vr->image) }}" class="img-thumbnail" style="max-width:200px;max-height:200px;object-fit:cover;">
        </a>
        @else
        <p class="text-muted">{{ _lang('No selfie') }}</p>
        @endif
    </div>
</div>

<hr>

<div class="row mt-3">
    <div class="col-md-6">
        <table class="table table-sm table-borderless">
            <tr><td><strong>{{ _lang('User') }}:</strong></td><td>{{ optional($vr->user)->name ?? '-' }}</td></tr>
            <tr><td><strong>{{ _lang('Email') }}:</strong></td><td>{{ optional($vr->user)->email ?? '-' }}</td></tr>
            <tr><td><strong>{{ _lang('Gender') }}:</strong></td><td>{{ optional(optional($vr->user)->user_information)->gender ?? '-' }}</td></tr>
            <tr><td><strong>{{ _lang('Country') }}:</strong></td><td>{{ optional(optional($vr->user)->user_information)->country_code ?? '-' }}</td></tr>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-sm table-borderless">
            <tr>
                <td><strong>{{ _lang('Status') }}:</strong></td>
                <td>
                    @if($vr->status === 'approved')
                        <span class="badge badge-success">{{ _lang('Approved') }}</span>
                    @elseif($vr->status === 'rejected')
                        <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                    @else
                        <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                    @endif
                </td>
            </tr>
            <tr><td><strong>{{ _lang('Submitted') }}:</strong></td><td>{{ $vr->created_at ? $vr->created_at->format('M d, Y H:i') : '-' }}</td></tr>
            @if($vr->reason)
            <tr><td><strong>{{ _lang('Reason') }}:</strong></td><td>{{ $vr->reason }}</td></tr>
            @endif
        </table>
    </div>
</div>

@if($vr->ai_response)
<div class="mt-3">
    <h6>{{ _lang('AI Analysis') }}</h6>
    <div class="bg-light p-3 rounded" style="max-height:200px;overflow-y:auto;">
        <pre class="mb-0" style="white-space:pre-wrap;font-size:12px;">{{ json_encode($vr->ai_response, JSON_PRETTY_PRINT) }}</pre>
    </div>
</div>
@endif

@if($vr->status === 'pending')
<hr>
<div class="row mt-3">
    <div class="col-md-6">
        <a href="{{ url('verification-requests/' . $vr->id . '/approve') }}" class="btn btn-success btn-block ajax-get-confirm" data-confirm="{{ _lang('Approve this verification?') }}">
            <i class="fas fa-check mr-1"></i>{{ _lang('Approve') }}
        </a>
    </div>
    <div class="col-md-6">
        <form method="post" class="ajax-submit" action="{{ url('verification-requests/' . $vr->id . '/reject') }}">
            @csrf
            <div class="form-group">
                <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ _lang('Rejection reason (optional)') }}">
            </div>
            <button type="submit" class="btn btn-danger btn-block">
                <i class="fas fa-times mr-1"></i>{{ _lang('Reject') }}
            </button>
        </form>
    </div>
</div>
@endif
