@extends('layouts.app')

@section('content')
<div class="row mb-3">
    <div class="col-12">
        <a href="{{ url('verification-queue') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Queue
        </a>
    </div>
</div>

@if($req->status !== 'review')
<div class="alert alert-info">
    <i class="fas fa-info-circle mr-1"></i>
    This verification has already been <strong>{{ $req->status }}</strong>.
</div>
@endif

{{-- Photo Comparison --}}
<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-user mr-1"></i> Profile Photos</div>
            <div class="card-body text-center">
                @if(count($profilePhotos) > 0)
                    <div class="d-flex flex-wrap justify-content-center" style="gap:8px;">
                        @foreach($profilePhotos as $photo)
                            <img src="{{ $photo }}" class="img-thumbnail" style="width:140px;height:140px;object-fit:cover;cursor:pointer;" onclick="window.open(this.src,'_blank')">
                        @endforeach
                    </div>
                @else
                    <p class="text-muted">No profile photos</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-2 d-flex align-items-center justify-content-center">
        <div class="text-center py-3">
            <i class="fas fa-arrows-alt-h text-muted" style="font-size:28px;"></i>
            <div class="mt-2">
                @if($highestMatch !== null)
                    @php
                        $score = round($highestMatch, 1);
                        $color = $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger');
                    @endphp
                    <span class="badge badge-{{ $color }}" style="font-size:20px;">{{ $score }}%</span>
                @else
                    <span class="badge badge-secondary" style="font-size:20px;">N/A</span>
                @endif
            </div>
            <small class="text-muted">Face Match</small>
            @if($totalCompared > 0)
                <br><small class="text-muted">{{ $matchedCount }}/{{ $totalCompared }} matched</small>
            @endif
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-camera mr-1"></i> Verification Selfie</div>
            <div class="card-body text-center">
                <img src="{{ asset($req->image) }}" class="img-thumbnail" style="max-height:300px;max-width:100%;object-fit:contain;cursor:pointer;" onclick="window.open(this.src,'_blank')">
            </div>
        </div>
    </div>
</div>

{{-- User Info + Reason --}}
<div class="row mt-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-id-card mr-1"></i> User Info</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted" style="width:100px;">Name</td><td><strong>{{ $user->name ?? 'Unknown' }}</strong></td></tr>
                    <tr><td class="text-muted">Email</td><td>{{ $user->email ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Gender</td><td>{{ ucfirst($info->gender ?? '-') }}</td></tr>
                    <tr>
                        <td class="text-muted">Age</td>
                        <td>{{ ($info && $info->date_of_birth) ? \Carbon\Carbon::parse($info->date_of_birth)->age : '-' }}</td>
                    </tr>
                    <tr><td class="text-muted">Country</td><td>{{ $info->country_code ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Joined</td><td>{{ $user->created_at ? $user->created_at->format('M d, Y') : '-' }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle mr-1"></i> Review Details</div>
            <div class="card-body">
                <div class="alert alert-warning mb-2">
                    <strong>Reason:</strong> {{ $req->reason }}
                </div>
                <small class="text-muted">Submitted: {{ $req->created_at ? $req->created_at->format('M d, Y g:ia') : '-' }}</small>
                <br>
                <small class="text-muted">Waiting: {{ $req->created_at ? $req->created_at->diffForHumans() : '-' }}</small>
            </div>
        </div>

        @if(count($faceDetails) > 0)
        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-chart-bar mr-1"></i> AWS Face Match Details</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light">
                        <tr><th>Photo</th><th>Result</th><th>Similarity</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                        @foreach($faceDetails as $d)
                        <tr>
                            <td>
                                @if($d['photo'])
                                    <img src="{{ $d['photo'] }}" class="img-thumbnail" style="width:40px;height:40px;object-fit:cover;">
                                @else - @endif
                            </td>
                            <td>
                                @if($d['result'] === 'matched')
                                    <span class="badge badge-success">Matched</span>
                                @elseif($d['result'] === 'unmatched')
                                    <span class="badge badge-danger">Unmatched</span>
                                @else
                                    <span class="badge badge-secondary">{{ $d['result'] }}</span>
                                @endif
                            </td>
                            <td>{{ $d['similarity'] !== null ? $d['similarity'] . '%' : '-' }}</td>
                            <td>{{ $d['reason'] ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Action Buttons --}}
@if($req->status === 'review')
<div class="row mt-4 mb-4">
    <div class="col-md-4 offset-md-1 mb-2">
        <button type="button" class="btn btn-success btn-lg btn-block" id="btn-approve">
            <i class="fas fa-check mr-1"></i> Approve Verification
        </button>
    </div>
    <div class="col-md-4 offset-md-2 mb-2">
        <button type="button" class="btn btn-danger btn-lg btn-block" id="btn-reject">
            <i class="fas fa-times mr-1"></i> Reject Verification
        </button>
    </div>
</div>

<div class="row mb-4" id="reject-reason-section" style="display:none;">
    <div class="col-md-6 offset-md-3">
        <div class="card border-danger">
            <div class="card-body">
                <label class="font-weight-bold text-danger">Rejection Reason:</label>
                <textarea id="reject-reason" class="form-control" rows="2" placeholder="Your verification photo did not pass review. Please try again with a clear selfie showing your face."></textarea>
                <button type="button" class="btn btn-danger btn-block mt-2" id="btn-confirm-reject">
                    <i class="fas fa-times mr-1"></i> Confirm Reject
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('js-script')
<script>
$(document).ready(function() {
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var reqId = {{ $req->id }};

    $('#btn-approve').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Approving...');

        $.ajax({
            url: '/verification-queue/' + reqId + '/approve',
            type: 'POST',
            data: { _token: csrf },
            success: function(res) {
                if (res.result === 'success') {
                    toastr.success(res.message);
                    setTimeout(function() { window.location.href = '{{ url("verification-queue") }}'; }, 1000);
                } else {
                    toastr.error(res.message);
                    btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Approve Verification');
                }
            },
            error: function() {
                toastr.error('Request failed.');
                btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Approve Verification');
            }
        });
    });

    $('#btn-reject').on('click', function() {
        $('#reject-reason-section').slideDown();
        $(this).hide();
    });

    $('#btn-confirm-reject').on('click', function() {
        var btn = $(this);
        var reason = $('#reject-reason').val().trim() || 'Your verification photo did not pass review. Please try again with a clear selfie showing your face.';
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Rejecting...');

        $.ajax({
            url: '/verification-queue/' + reqId + '/reject',
            type: 'POST',
            data: { _token: csrf, reason: reason },
            success: function(res) {
                if (res.result === 'success') {
                    toastr.warning(res.message);
                    setTimeout(function() { window.location.href = '{{ url("verification-queue") }}'; }, 1000);
                } else {
                    toastr.error(res.message);
                    btn.prop('disabled', false).html('<i class="fas fa-times mr-1"></i> Confirm Reject');
                }
            },
            error: function() {
                toastr.error('Request failed.');
                btn.prop('disabled', false).html('<i class="fas fa-times mr-1"></i> Confirm Reject');
            }
        });
    });
});
</script>
@endsection
