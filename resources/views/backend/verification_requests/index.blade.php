@extends('layouts.app')
@section('content')
<div class="row">
    <div class="col-md-12 breadcrumb-box">
        <h4 class="card-title">{{ _lang('Verification Requests') }}</h4>
    </div>

    {{-- Filters --}}
    <div class="col-md-12 mb-3">
        <div class="card">
            <div class="card-body py-2">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <select class="form-control form-control-sm select2" id="filter-status">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary btn-sm" id="btn-filter">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <button class="btn btn-secondary btn-sm ml-1" id="btn-reset">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="col-md-12">
        <div class="card">
            <div class="card-body p-0 p-md-3">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" id="data-table">
                        <thead>
                            <tr>
                                <th style="width:80px;">Profile</th>
                                <th>User</th>
                                <th style="width:80px;">Selfie</th>
                                <th style="width:100px;">Status</th>
                                <th>Date</th>
                                <th class="text-center" style="width:180px;">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Full Profile Review Modal --}}
<div class="modal fade" id="review-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-id-card mr-2"></i>Verification Review</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="review-modal-body">
                <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
var csrf = $('meta[name="csrf-token"]').attr('content');

var table = $('#data-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: _url + '/verification-requests',
        data: function(d) {
            d.filter_status = $('#filter-status').val();
        }
    },
    columns: [
        { data: 'user_image',  name: 'user_image',  orderable: false, searchable: false },
        { data: 'user_name',   name: 'user_name' },
        { data: 'image',       name: 'image',       orderable: false, searchable: false },
        { data: 'status',      name: 'status' },
        { data: 'created_at',  name: 'created_at' },
        { data: 'action',      name: 'action',      orderable: false, searchable: false, className: 'text-center' }
    ],
    responsive: true,
    bStateSave: true,
    bAutoWidth: false,
    ordering: false
});

$('#btn-filter').on('click', function() { table.ajax.reload(); });
$('#btn-reset').on('click', function() {
    $('#filter-status').val('').trigger('change');
    table.ajax.reload();
});

// Open full profile modal
$(document).on('click', '.btn-review', function() {
    var id = $(this).data('id');
    $('#review-modal-body').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#review-modal').modal('show');
    $.get(_url + '/verification-requests/' + id, function(html) {
        $('#review-modal-body').html(html);
    });
});

// Quick Approve inline
$(document).on('click', '.btn-quick-approve', function() {
    var id  = $(this).data('id');
    var btn = $(this);
    if (!confirm('Approve this verification?')) return;
    btn.prop('disabled', true);
    $.ajax({ url: _url + '/verification-requests/' + id + '/approve', method: 'GET',
        success: function(res) {
            toastr.success(res.message || 'Approved');
            table.ajax.reload(null, false);
        },
        error: function() { toastr.error('Failed to approve'); btn.prop('disabled', false); }
    });
});

// Quick Reject inline (opens small prompt)
$(document).on('click', '.btn-quick-reject', function() {
    var id = $(this).data('id');
    var reason = prompt('Rejection reason (optional):') || '';
    $.post(_url + '/verification-requests/' + id + '/reject', { _token: csrf, reason: reason },
        function(res) {
            toastr.warning(res.message || 'Rejected');
            table.ajax.reload(null, false);
        }
    ).fail(function() { toastr.error('Failed to reject'); });
});
</script>
@endsection
