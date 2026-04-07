@extends('layouts.app')
@section('content')
<div class="row">
    <div class="col-md-12 breadcrumb-box">
        <h4 class="card-title">{{ _lang('Verification Queue') }}</h4>
    </div>

    {{-- Stats Cards --}}
    <div class="col-md-12 mb-3">
        <div class="row" id="stats-row">
            <div class="col-md-2 col-6 mb-2">
                <div class="card bg-warning text-white">
                    <div class="card-body p-3 text-center">
                        <h5 class="mb-0" id="stat-pending">-</h5>
                        <small>{{ _lang('Pending') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-2">
                <div class="card bg-success text-white">
                    <div class="card-body p-3 text-center">
                        <h5 class="mb-0" id="stat-approved">-</h5>
                        <small>{{ _lang('Approved') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-2">
                <div class="card bg-danger text-white">
                    <div class="card-body p-3 text-center">
                        <h5 class="mb-0" id="stat-rejected">-</h5>
                        <small>{{ _lang('Rejected') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-2">
                <div class="card bg-info text-white">
                    <div class="card-body p-3 text-center">
                        <h5 class="mb-0" id="stat-avg-confidence">-</h5>
                        <small>{{ _lang('Avg Confidence') }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="col-md-12 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Filter by Status') }}</label>
                            <select class="form-control select2" name="filter_status" id="filter-status">
                                <option value="">{{ _lang('All') }}</option>
                                <option value="pending" selected>{{ _lang('Pending') }}</option>
                                <option value="approved">{{ _lang('Approved') }}</option>
                                <option value="rejected">{{ _lang('Rejected') }}</option>
                                <option value="auto_approved">{{ _lang('Auto-Approved') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Min Confidence') }}</label>
                            <input type="number" class="form-control" id="filter-confidence-min" min="0" max="100" step="5" placeholder="0%">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Max Confidence') }}</label>
                            <input type="number" class="form-control" id="filter-confidence-max" min="0" max="100" step="5" placeholder="100%">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mt-4">
                            <button class="btn btn-primary btn-sm" id="btn-filter">{{ _lang('Filter') }}</button>
                            <button class="btn btn-info btn-sm" id="btn-reset">{{ _lang('Reset') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-12">
        <div class="card">
            <div class="card-body p-0 p-md-3">
                <div class="table-responsive">
                <table class="table table-bordered mb-0" id="data-table">
                    <thead>
                        <tr>
                            <th>{{ _lang('User') }}</th>
                            <th>{{ _lang('AI Confidence') }}</th>
                            <th>{{ _lang('Status') }}</th>
                            <th>{{ _lang('Date') }}</th>
                            <th class="text-center">{{ _lang('Action') }}</th>
                        </tr>
                    </thead>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- View Detail Modal --}}
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Verification Details') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Reject Reason Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Reject Verification') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reject-id">
                <div class="form-group">
                    <label>{{ _lang('Rejection Reason') }}</label>
                    <textarea class="form-control" id="reject-reason" rows="3" maxlength="500" required placeholder="{{ _lang('Enter reason for rejection...') }}"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ _lang('Cancel') }}</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-confirm-reject">{{ _lang('Reject') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
// Load stats
function loadStats() {
    $.get(_url + '/verification-queue/stats', function(res) {
        if (res.status) {
            $('#stat-pending').text(res.data.pending);
            $('#stat-approved').text(res.data.approved);
            $('#stat-rejected').text(res.data.rejected);
            $('#stat-avg-confidence').text(res.data.avg_confidence);
        }
    });
}
loadStats();

// DataTable
var table = $('#data-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: _url + "/verification-queue",
        data: function(d) {
            d.status = $('#filter-status').val();
            var minVal = $('#filter-confidence-min').val();
            var maxVal = $('#filter-confidence-max').val();
            if (minVal) d.confidence_min = minVal / 100;
            if (maxVal) d.confidence_max = maxVal / 100;
        }
    },
    columns: [
        { data: "user_name", name: "user_name" },
        { data: "confidence_percent", name: "ai_confidence" },
        { data: "status_badge", name: "status", orderable: false, searchable: false },
        { data: "created_at", name: "created_at" },
        { data: "action", name: "action", orderable: false, searchable: false, className: "text-center" }
    ],
    responsive: true,
    bStateSave: true,
    bAutoWidth: false,
    order: [[3, 'desc']]
});

// Filter
$('#btn-filter').on('click', function() { table.ajax.reload(); });
$('#btn-reset').on('click', function() {
    $('#filter-status').val('').trigger('change');
    $('#filter-confidence-min').val('');
    $('#filter-confidence-max').val('');
    table.ajax.reload();
});

// View detail
$(document).on('click', '.view-btn', function() {
    var id = $(this).data('id');
    $('#viewModalBody').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#viewModal').modal('show');
    $.get(_url + '/verification-queue/' + id, function(res) {
        if (res.status) {
            var d = res.data;
            var html = '<div class="row">';
            html += '<div class="col-md-5 text-center">';
            html += '<h6>{{ _lang("Profile Photo") }}</h6>';
            if (d.user.image) html += '<img src="' + d.user.image + '" class="img-thumbnail" style="max-width:200px;max-height:200px;object-fit:cover;">';
            else html += '<p class="text-muted">{{ _lang("No profile photo") }}</p>';
            html += '</div>';
            html += '<div class="col-md-2 text-center d-flex align-items-center justify-content-center"><i class="fas fa-arrows-alt-h fa-2x text-muted"></i></div>';
            html += '<div class="col-md-5 text-center">';
            html += '<h6>{{ _lang("Verification Selfie") }}</h6>';
            if (d.selfie_image) html += '<a href="' + d.selfie_image + '" target="_blank"><img src="' + d.selfie_image + '" class="img-thumbnail" style="max-width:200px;max-height:200px;object-fit:cover;"></a>';
            else html += '<p class="text-muted">{{ _lang("No selfie") }}</p>';
            html += '</div></div><hr>';

            html += '<div class="row mt-3"><div class="col-md-6">';
            html += '<table class="table table-sm table-borderless">';
            html += '<tr><td><strong>{{ _lang("User") }}:</strong></td><td>' + (d.user.name || '-') + '</td></tr>';
            html += '<tr><td><strong>{{ _lang("Email") }}:</strong></td><td>' + (d.user.email || '-') + '</td></tr>';
            html += '<tr><td><strong>{{ _lang("AI Confidence") }}:</strong></td><td><span class="badge badge-info">' + d.ai_confidence + '</span></td></tr>';
            html += '</table></div>';

            html += '<div class="col-md-6"><table class="table table-sm table-borderless">';
            html += '<tr><td><strong>{{ _lang("Status") }}:</strong></td><td>' + d.status + '</td></tr>';
            html += '<tr><td><strong>{{ _lang("Submitted") }}:</strong></td><td>' + d.created_at + '</td></tr>';
            if (d.approved_by) html += '<tr><td><strong>{{ _lang("Reviewed By") }}:</strong></td><td>' + d.approved_by + '</td></tr>';
            if (d.approved_at) html += '<tr><td><strong>{{ _lang("Reviewed At") }}:</strong></td><td>' + d.approved_at + '</td></tr>';
            if (d.reason) html += '<tr><td><strong>{{ _lang("Reason") }}:</strong></td><td>' + d.reason + '</td></tr>';
            if (d.notes) html += '<tr><td><strong>{{ _lang("Admin Notes") }}:</strong></td><td>' + d.notes + '</td></tr>';
            html += '</table></div></div>';

            if (d.ai_response) {
                html += '<div class="mt-3"><h6>{{ _lang("AI Analysis") }}</h6>';
                html += '<div class="bg-light p-3 rounded" style="max-height:200px;overflow-y:auto;">';
                html += '<pre class="mb-0" style="white-space:pre-wrap;font-size:12px;">' + JSON.stringify(d.ai_response, null, 2) + '</pre>';
                html += '</div></div>';
            }

            if (d.status === 'pending') {
                html += '<hr><div class="row mt-3"><div class="col-md-6">';
                html += '<button class="btn btn-success btn-block approve-btn" data-id="' + d.id + '"><i class="fas fa-check mr-1"></i>{{ _lang("Approve") }}</button>';
                html += '</div><div class="col-md-6">';
                html += '<button class="btn btn-danger btn-block reject-btn" data-id="' + d.id + '"><i class="fas fa-times mr-1"></i>{{ _lang("Reject") }}</button>';
                html += '</div></div>';
            }

            $('#viewModalBody').html(html);
        }
    });
});

// Approve
$(document).on('click', '.approve-btn', function() {
    var id = $(this).data('id');
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $.ajax({
        url: _url + '/verification-queue/' + id + '/approve',
        method: 'POST',
        data: { _token: $('meta[name="csrf-token"]').attr('content') },
        success: function(res) {
            if (res.status) {
                Swal.fire('Success', res.message, 'success');
                table.ajax.reload(null, false);
                loadStats();
                $('#viewModal').modal('hide');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
            btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>{{ _lang("Approve") }}');
        },
        error: function(xhr) {
            Swal.fire('Error', xhr.responseJSON?.message || 'Something went wrong', 'error');
            btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>{{ _lang("Approve") }}');
        }
    });
});

// Reject - open modal
$(document).on('click', '.reject-btn', function() {
    var id = $(this).data('id');
    $('#reject-id').val(id);
    $('#reject-reason').val('');
    $('#rejectModal').modal('show');
});

// Confirm reject
$('#btn-confirm-reject').on('click', function() {
    var id = $('#reject-id').val();
    var reason = $('#reject-reason').val();
    if (!reason) { Swal.fire('Error', '{{ _lang("Please enter a rejection reason") }}', 'error'); return; }

    var btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $.ajax({
        url: _url + '/verification-queue/' + id + '/reject',
        method: 'POST',
        data: { _token: $('meta[name="csrf-token"]').attr('content'), reason: reason },
        success: function(res) {
            if (res.status) {
                Swal.fire('Success', res.message, 'success');
                table.ajax.reload(null, false);
                loadStats();
                $('#rejectModal').modal('hide');
                $('#viewModal').modal('hide');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
            btn.prop('disabled', false).text('{{ _lang("Reject") }}');
        },
        error: function(xhr) {
            Swal.fire('Error', xhr.responseJSON?.message || 'Something went wrong', 'error');
            btn.prop('disabled', false).text('{{ _lang("Reject") }}');
        }
    });
});
</script>
@endsection
