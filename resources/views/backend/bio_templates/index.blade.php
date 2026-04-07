@extends('layouts.app')
@section('content')
<div class="row">
    <div class="col-md-6 breadcrumb-box"></div>
    <div class="col-md-6 mb-2 text-right">
        <h4 class="card-title d-none">{{ _lang('Bio Templates') }}</h4>
        <a class="btn btn-primary btn-sm ajax-modal" href="{{ route('bio_templates.create') }}" data-title="{{ _lang('Add Bio Template') }}">
            <i class="fas fa-plus mr-1"></i>{{ _lang('Add New') }}
        </a>
    </div>
    <div class="col-md-12">
        <div class="card">
            <div class="card-body p-0 p-md-3">
                <div class="table-responsive">
                <table class="table table-bordered mb-0" id="data-table">
                    <thead>
                        <tr>
                            <th>{{ _lang('Text') }}</th>
                            <th>{{ _lang('Gender') }}</th>
                            <th>{{ _lang('Sort Order') }}</th>
                            <th>{{ _lang('Status') }}</th>
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
    ajax: _url + "/bio_templates",
    columns: [
        { data: "text", name: "text" },
        { data: "gender", name: "gender" },
        { data: "sort_order", name: "sort_order" },
        { data: "is_active", name: "is_active" },
        { data: "action", name: "action", orderable: false, searchable: false, className: "text-center" }
    ],
    responsive: true,
    bStateSave: true,
    bAutoWidth: false,
    ordering: false
});
</script>
@endsection
