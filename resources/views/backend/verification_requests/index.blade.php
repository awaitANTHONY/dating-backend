@extends('layouts.app')
@section('content')
<div class="row">
    <div class="col-md-12 breadcrumb-box">
        <h4 class="card-title">{{ _lang('Verification Requests') }}</h4>
    </div>
    <div class="col-md-12 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Filter by Status') }}</label>
                            <select class="form-control select2" name="filter_status" id="filter-status">
                                <option value="">{{ _lang('All') }}</option>
                                <option value="pending">{{ _lang('Pending') }}</option>
                                <option value="approved">{{ _lang('Approved') }}</option>
                                <option value="rejected">{{ _lang('Rejected') }}</option>
                            </select>
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
                            <th>{{ _lang('Name') }}</th>
                            <th>{{ _lang('Selfie') }}</th>
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
@endsection
@section('js-script')
<script>
$('#data-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: _url + "/verification-requests",
        data: function(d) {
            d.filter_status = $('#filter-status').val();
        }
    },
    columns: [
        { data: "user_image", name: "user_image", orderable: false, searchable: false },
        { data: "user_name", name: "user_name" },
        { data: "image", name: "image", orderable: false, searchable: false },
        { data: "status", name: "status" },
        { data: "created_at", name: "created_at" },
        { data: "action", name: "action", orderable: false, searchable: false, className: "text-center" }
    ],
    responsive: true,
    bStateSave: true,
    bAutoWidth: false,
    ordering: false
});

$('#btn-filter').on('click', function() {
    $('#data-table').DataTable().ajax.reload();
});

$('#btn-reset').on('click', function() {
    $('#filter-status').val('').trigger('change');
    $('#data-table').DataTable().ajax.reload();
});
</script>
@endsection
