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
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    var csrf = $('meta[name="csrf-token"]').attr('content');

    var table = $('#verification-queue-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '{{ url("verification-queue") }}', type: 'GET' },
        order: [[9, 'asc']],
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

    // Select All
    $('#select-all').on('change', function() {
        $('.row-select').prop('checked', this.checked);
        updateBulkButton();
    });
    $(document).on('change', '.row-select', function() { updateBulkButton(); });

    function updateBulkButton() {
        var count = $('.row-select:checked').length;
        $('#selected-count').text(count);
        $('#btn-bulk-approve').toggle(count > 0);
    }

    // Inline Approve
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
                    if (res.remaining == 0) { $('#table-wrapper').hide(); $('#empty-state').show(); }
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

    // Inline Reject
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
                    if (res.remaining == 0) { $('#table-wrapper').hide(); $('#empty-state').show(); }
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

    // Bulk Approve
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
                    if (res.remaining == 0) { $('#table-wrapper').hide(); $('#empty-state').show(); }
                } else { toastr.error(res.message); }
                btn.prop('disabled', false).html('<i class="fas fa-check-double"></i> Approve Selected (<span id="selected-count">0</span>)');
            },
            error: function() {
                toastr.error('Bulk approve failed.');
                btn.prop('disabled', false).html('<i class="fas fa-check-double"></i> Approve Selected');
            }
        });
    });

    // Refresh
    $('#btn-refresh').on('click', function() { table.ajax.reload(null, false); });
});
</script>
@endsection
