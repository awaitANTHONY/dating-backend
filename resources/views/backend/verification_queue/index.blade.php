@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="header-title mb-0">
                    <i class="fas fa-user-check mr-2"></i>Verification Review Queue
                    <span id="queue-count" class="badge badge-warning ml-2" style="font-size:14px;">{{ $reviewCount }}</span>
                </h4>
                <div>
                    <button class="btn btn-success btn-sm" id="btn-bulk-approve" style="display:none;">
                        <i class="fas fa-check-double"></i> Approve Selected (<span id="selected-count">0</span>)
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="btn-refresh">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                @if($reviewCount == 0)
                <div id="empty-state" class="text-center py-5">
                    <i class="fas fa-check-circle text-success" style="font-size:48px;"></i>
                    <h5 class="mt-3 text-muted">All clear! No pending reviews.</h5>
                </div>
                @endif

                <div class="table-responsive" id="table-wrapper" @if($reviewCount == 0) style="display:none;" @endif>
                    <table class="table table-hover" id="verification-queue-table">
                        <thead>
                            <tr>
                                <th style="width:30px;"><input type="checkbox" id="select-all"></th>
                                <th>Profile</th>
                                <th>Selfie</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Country</th>
                                <th>Face Match</th>
                                <th>Matched</th>
                                <th>Waiting</th>
                                <th style="width:150px;">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Review Detail Modal ─────────────────────────────────────────── -->
<div class="modal fade" id="reviewModal" tabindex="-1" role="dialog" aria-labelledby="reviewModalLabel" style="z-index:1060;">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="reviewModalLabel">
                    <i class="fas fa-user-check mr-2"></i>Review Verification
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="modal-body" style="overflow-y:auto;max-height:70vh;">
                <!-- Content loaded via AJAX -->
                <div class="text-center py-4" id="modal-loading">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2 text-muted">Loading verification data...</p>
                </div>
                <div id="modal-content" style="display:none;">
                    <!-- Photo Comparison -->
                    <div class="row mb-4">
                        <div class="col-md-5 text-center">
                            <h6 class="text-muted mb-2"><i class="fas fa-user mr-1"></i> Profile Photos</h6>
                            <div id="profile-photos-grid" class="d-flex flex-wrap justify-content-center gap-2" style="gap:8px;">
                                <!-- Profile photos loaded here -->
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-center justify-content-center">
                            <div class="text-center">
                                <i class="fas fa-arrows-alt-h text-muted" style="font-size:28px;"></i>
                                <div class="mt-2">
                                    <span id="modal-face-score" class="badge" style="font-size:18px;">-</span>
                                </div>
                                <small class="text-muted">Face Match</small>
                            </div>
                        </div>
                        <div class="col-md-5 text-center">
                            <h6 class="text-muted mb-2"><i class="fas fa-camera mr-1"></i> Verification Selfie</h6>
                            <img id="modal-selfie" src="" class="img-thumbnail" style="max-height:300px;max-width:100%;object-fit:contain;">
                        </div>
                    </div>

                    <!-- User Info -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted" style="width:100px;">User:</td><td><strong id="modal-name"></strong></td></tr>
                                <tr><td class="text-muted">Email:</td><td id="modal-email"></td></tr>
                                <tr><td class="text-muted">Gender:</td><td id="modal-gender"></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted" style="width:100px;">Age:</td><td id="modal-age"></td></tr>
                                <tr><td class="text-muted">Country:</td><td id="modal-country"></td></tr>
                                <tr><td class="text-muted">Joined:</td><td id="modal-joined"></td></tr>
                            </table>
                        </div>
                    </div>

                    <!-- Face Matching Details Table -->
                    <div id="face-details-section" style="display:none;">
                        <h6 class="text-muted mb-2"><i class="fas fa-chart-bar mr-1"></i> AWS Face Matching Details</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="face-details-table">
                                <thead class="thead-light">
                                    <tr><th>Photo</th><th>Result</th><th>Similarity</th><th>Notes</th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Review Reason -->
                    <div class="alert alert-warning mt-3 mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Reason for Review:</strong>
                        <span id="modal-reason"></span>
                    </div>

                    <!-- Rejection Reason Input (hidden by default) -->
                    <div id="reject-reason-section" style="display:none;" class="mb-3">
                        <label for="reject-reason" class="font-weight-bold text-danger">Rejection Reason:</label>
                        <textarea id="reject-reason" class="form-control" rows="2" placeholder="Your verification photo did not pass review. Please try again with a clear selfie showing your face."></textarea>
                    </div>

                </div>
            </div>
            <div class="modal-footer" style="display:flex;justify-content:center;gap:10px;">
                <button type="button" class="btn btn-success btn-lg" id="modal-approve-btn" style="min-width:200px;">
                    <i class="fas fa-check mr-1"></i> Approve Verification
                </button>
                <button type="button" class="btn btn-danger btn-lg" id="modal-reject-btn" style="min-width:200px;">
                    <i class="fas fa-times mr-1"></i> Reject Verification
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var currentReviewId = null;

    // ── DataTable ──────────────────────────────────────────────────
    var table = $('#verification-queue-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ url("verification-queue") }}',
            type: 'GET'
        },
        order: [[9, 'asc']], // Waiting (oldest first)
        pageLength: 25,
        columns: [
            {
                data: null, orderable: false, searchable: false,
                render: function(data) {
                    return '<input type="checkbox" class="row-select" value="' + data.id + '">';
                }
            },
            { data: 'user_image', name: 'user_image', orderable: false, searchable: false },
            { data: 'selfie', name: 'selfie', orderable: false, searchable: false },
            { data: 'user_name', name: 'user.name' },
            { data: 'gender', name: 'gender', orderable: false, searchable: false },
            { data: 'age', name: 'age', orderable: false, searchable: false },
            { data: 'country', name: 'country', orderable: false, searchable: false },
            { data: 'face_score', name: 'face_score', orderable: false, searchable: false },
            { data: 'matched_count', name: 'matched_count', orderable: false, searchable: false },
            { data: 'waiting', name: 'waiting', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
        language: { emptyTable: "No verifications awaiting review." }
    });

    // ── Select All ────────────────────────────────────────────────
    $('#select-all').on('change', function() {
        $('.row-select').prop('checked', this.checked);
        updateBulkButton();
    });

    $(document).on('change', '.row-select', function() {
        updateBulkButton();
    });

    function updateBulkButton() {
        var count = $('.row-select:checked').length;
        $('#selected-count').text(count);
        $('#btn-bulk-approve').toggle(count > 0);
    }

    // ── Inline Approve ────────────────────────────────────────────
    $(document).on('click', '.btn-approve', function() {
        var id = $(this).data('id');
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: '/verification-queue/' + id + '/approve',
            type: 'POST',
            data: { _token: csrf },
            success: function(res) {
                if (res.result === 'success') {
                    toastr.success(res.message);
                    table.ajax.reload(null, false);
                    $('#queue-count').text(res.remaining);
                    if (res.remaining == 0) {
                        $('#table-wrapper').hide();
                        $('#empty-state').show();
                    }
                } else {
                    toastr.error(res.message);
                    btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                }
            },
            error: function() {
                toastr.error('Request failed.');
                btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
            }
        });
    });

    // ── Inline Reject ─────────────────────────────────────────────
    $(document).on('click', '.btn-reject', function() {
        var id = $(this).data('id');
        var btn = $(this);

        if (!confirm('Reject this verification? The user will be notified.')) return;

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: '/verification-queue/' + id + '/reject',
            type: 'POST',
            data: { _token: csrf },
            success: function(res) {
                if (res.result === 'success') {
                    toastr.warning(res.message);
                    table.ajax.reload(null, false);
                    $('#queue-count').text(res.remaining);
                    if (res.remaining == 0) {
                        $('#table-wrapper').hide();
                        $('#empty-state').show();
                    }
                } else {
                    toastr.error(res.message);
                    btn.prop('disabled', false).html('<i class="fas fa-times"></i>');
                }
            },
            error: function() {
                toastr.error('Request failed.');
                btn.prop('disabled', false).html('<i class="fas fa-times"></i>');
            }
        });
    });

    // ── Detail Modal ──────────────────────────────────────────────
    $(document).on('click', '.btn-detail', function() {
        var id = $(this).data('id');
        currentReviewId = id;
        $('#modal-loading').show();
        $('#modal-content').hide();
        $('#reject-reason-section').hide();
        $('#reviewModal').modal('show');

        $.get('/verification-queue/' + id, function(data) {
            // Selfie
            $('#modal-selfie').attr('src', data.selfie_url);

            // Profile photos grid
            var grid = $('#profile-photos-grid');
            grid.empty();
            if (data.profile_photos && data.profile_photos.length > 0) {
                data.profile_photos.forEach(function(url) {
                    grid.append(
                        '<img src="' + url + '" class="img-thumbnail" ' +
                        'style="width:120px;height:120px;object-fit:cover;cursor:pointer;" ' +
                        'onclick="window.open(this.src, \'_blank\')">'
                    );
                });
            } else {
                grid.html('<p class="text-muted">No profile photos available</p>');
            }

            // Face match score badge
            var scoreEl = $('#modal-face-score');
            if (data.highest_match !== null && data.highest_match !== undefined) {
                var score = parseFloat(data.highest_match);
                var color = score >= 80 ? 'success' : (score >= 60 ? 'warning' : 'danger');
                scoreEl.text(score.toFixed(1) + '%').removeClass().addClass('badge badge-' + color);
            } else {
                scoreEl.text('N/A').removeClass().addClass('badge badge-secondary');
            }

            // User info
            $('#modal-name').text(data.user_name);
            $('#modal-email').text(data.user_email);
            $('#modal-gender').text(data.gender);
            $('#modal-age').text(data.age || '-');
            $('#modal-country').text(data.country);
            $('#modal-joined').text(data.joined);
            $('#modal-reason').text(data.reason);

            // Face matching details table
            var tbody = $('#face-details-table tbody');
            tbody.empty();
            if (data.face_details && data.face_details.length > 0) {
                $('#face-details-section').show();
                data.face_details.forEach(function(d) {
                    var photoCell = d.photo
                        ? '<img src="' + d.photo + '" style="width:40px;height:40px;object-fit:cover;" class="img-thumbnail">'
                        : '-';
                    var resultBadge = '';
                    if (d.result === 'matched') {
                        resultBadge = '<span class="badge badge-success">Matched</span>';
                    } else if (d.result === 'unmatched') {
                        resultBadge = '<span class="badge badge-danger">Unmatched</span>';
                    } else {
                        resultBadge = '<span class="badge badge-secondary">' + d.result + '</span>';
                    }
                    var simCell = d.similarity !== null && d.similarity !== undefined
                        ? d.similarity + '%'
                        : '-';
                    tbody.append(
                        '<tr><td>' + photoCell + '</td><td>' + resultBadge + '</td><td>' + simCell + '</td><td>' + (d.reason || '-') + '</td></tr>'
                    );
                });
            } else {
                $('#face-details-section').hide();
            }

            $('#modal-loading').hide();
            $('#modal-content').show();
        }).fail(function() {
            $('#modal-loading').html('<div class="alert alert-danger">Failed to load verification details.</div>');
        });
    });

    // ── Modal Approve ─────────────────────────────────────────────
    $(document).on('click', '#modal-approve-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!currentReviewId) return;
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Approving...');

        $.ajax({
            url: '/verification-queue/' + currentReviewId + '/approve',
            type: 'POST',
            data: { _token: csrf },
            success: function(res) {
                if (res.result === 'success') {
                    toastr.success(res.message);
                    $('#reviewModal').modal('hide');
                    table.ajax.reload(null, false);
                    $('#queue-count').text(res.remaining);
                    if (res.remaining == 0) {
                        $('#table-wrapper').hide();
                        $('#empty-state').show();
                    }
                } else {
                    toastr.error(res.message);
                }
                btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Approve Verification');
            },
            error: function() {
                toastr.error('Request failed.');
                btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Approve Verification');
            }
        });
    });

    // ── Modal Reject ──────────────────────────────────────────────
    $(document).on('click', '#modal-reject-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var section = $('#reject-reason-section');
        if (section.is(':hidden')) {
            // First click: show reason input
            section.slideDown();
            $(this).html('<i class="fas fa-times mr-1"></i> Confirm Reject');
            return;
        }

        // Second click: submit rejection
        if (!currentReviewId) return;
        var btn = $(this);
        var reason = $('#reject-reason').val().trim() || 'Your verification photo did not pass review. Please try again with a clear selfie showing your face.';
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Rejecting...');

        $.ajax({
            url: '/verification-queue/' + currentReviewId + '/reject',
            type: 'POST',
            data: { _token: csrf, reason: reason },
            success: function(res) {
                if (res.result === 'success') {
                    toastr.warning(res.message);
                    $('#reviewModal').modal('hide');
                    table.ajax.reload(null, false);
                    $('#queue-count').text(res.remaining);
                    if (res.remaining == 0) {
                        $('#table-wrapper').hide();
                        $('#empty-state').show();
                    }
                } else {
                    toastr.error(res.message);
                }
                btn.prop('disabled', false).html('<i class="fas fa-times mr-1"></i> Reject Verification');
                section.slideUp();
                $('#reject-reason').val('');
            },
            error: function() {
                toastr.error('Request failed.');
                btn.prop('disabled', false).html('<i class="fas fa-times mr-1"></i> Reject Verification');
            }
        });
    });

    // ── Bulk Approve ──────────────────────────────────────────────
    $('#btn-bulk-approve').on('click', function() {
        var ids = [];
        $('.row-select:checked').each(function() { ids.push($(this).val()); });
        if (ids.length === 0) return;

        if (!confirm('Approve ' + ids.length + ' verification(s)?')) return;

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: '/verification-queue/bulk-approve',
            type: 'POST',
            data: { _token: csrf, ids: ids },
            success: function(res) {
                if (res.result === 'success') {
                    toastr.success(res.message);
                    table.ajax.reload(null, false);
                    $('#queue-count').text(res.remaining);
                    $('#select-all').prop('checked', false);
                    updateBulkButton();
                    if (res.remaining == 0) {
                        $('#table-wrapper').hide();
                        $('#empty-state').show();
                    }
                } else {
                    toastr.error(res.message);
                }
                btn.prop('disabled', false).html('<i class="fas fa-check-double"></i> Approve Selected (<span id="selected-count">0</span>)');
            },
            error: function() {
                toastr.error('Bulk approve failed.');
                btn.prop('disabled', false).html('<i class="fas fa-check-double"></i> Approve Selected');
            }
        });
    });

    // ── Refresh ───────────────────────────────────────────────────
    $('#btn-refresh').on('click', function() {
        table.ajax.reload(null, false);
        $.get('{{ url("verification-queue") }}', { _: Date.now() }, function() {}).fail(function(){});
    });

    // ── Keyboard Shortcuts ────────────────────────────────────────
    $(document).on('keydown', function(e) {
        if ($('#reviewModal').hasClass('show') && currentReviewId) {
            if (e.key === 'a' || e.key === 'A') {
                e.preventDefault();
                $('#modal-approve-btn').click();
            }
        }
    });
});
</script>
@endsection
