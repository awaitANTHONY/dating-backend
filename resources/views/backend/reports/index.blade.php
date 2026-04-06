@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-md-6 breadcrumb-box"></div>
	<div class="col-md-6 mb-2 text-right">
		<h4 class="card-title d-none">{{ _lang('User Reports') }}</h4>
	</div>

	{{-- Status Filter --}}
	<div class="col-12 mb-3">
		<div class="d-flex flex-wrap" style="gap:4px;">
			<a href="{{ url('reports') }}" class="btn btn-sm {{ !request('status') ? 'btn-primary' : 'btn-outline-primary' }}">
				{{ _lang('All') }}
			</a>
			<a href="{{ url('reports?status=pending') }}" class="btn btn-sm {{ request('status') == 'pending' ? 'btn-warning' : 'btn-outline-warning' }}">
				{{ _lang('Pending') }}
			</a>
			<a href="{{ url('reports?status=reviewed') }}" class="btn btn-sm {{ request('status') == 'reviewed' ? 'btn-success' : 'btn-outline-success' }}">
				{{ _lang('Reviewed') }}
			</a>
			<a href="{{ url('reports?status=dismissed') }}" class="btn btn-sm {{ request('status') == 'dismissed' ? 'btn-secondary' : 'btn-outline-secondary' }}">
				{{ _lang('Dismissed') }}
			</a>
		</div>
	</div>

	<div class="col-md-12">
		<div class="card">
			<div class="card-body p-0 p-md-3">
				<div class="table-responsive">
				<table class="table table-bordered mb-0" id="reports-table">
					<thead>
						<tr>
							<th>{{ _lang('Reporter') }}</th>
							<th>{{ _lang('Reported User') }}</th>
							<th>{{ _lang('Reason') }}</th>
							<th>{{ _lang('Total Reports') }}</th>
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
	$('#reports-table').DataTable({
		processing: true,
		serverSide: true,
		ajax: {
			url: _url + "/reports",
			data: function(d) {
				var urlParams = new URLSearchParams(window.location.search);
				if (urlParams.has('status')) {
					d.status = urlParams.get('status');
				}
			}
		},
		columns: [
			{ data: 'reporter_name', name: 'reporter_name' },
			{ data: 'reported_user_name', name: 'reported_user_name' },
			{ data: 'reason', name: 'reason' },
			{ data: 'report_count', name: 'report_count', searchable: false },
			{ data: 'status', name: 'status' },
			{ data: 'created_at', name: 'created_at' },
			{ data: 'action', name: 'action', orderable: false, searchable: false },
		],
		order: [[5, 'desc']],
	});
</script>
@endsection
