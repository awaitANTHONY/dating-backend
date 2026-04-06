@extends('layouts.app')

@section('content')

<div class="row">

	<div class="col-md-6 breadcrumb-box"></div>
    {{-- <div class="col-md-6 mb-2 text-right">
		<h2 class="card-title d-none">{{ _lang('User List') }}</h2>
		<a class="btn btn-primary btn-sm ajax-modal" href="{{ route('users.create') }}" data-title="{{ _lang('Add New') }}">
			<i class="fas fa-plus mr-1"></i>
			{{ _lang('Add New') }}
		</a>
	</div> --}}
	<div class="col-md-12 mb-5">
		<div class="card">
			<div class="card-body">
				<form method="get" autocomplete="off" action="{{ url('users') }}" id="user-filter-form">
				<div class="row">
					<div class="col-md-3">
						<div class="form-group">
							<label class="control-label">{{ _lang('Search by') }}</label>
							<select class="form-control select2" name="search_by" data-selected="{{ request('search_by') }}">
								<option value="">{{ _lang('Select One') }}</option>
								<option value="name">{{ _lang('Name') }}</option>
								<option value="email">{{ _lang('Email') }}</option>
							</select>
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group">
							<label class="control-label">{{ _lang('Search') }}</label>
							<input type="text" class="form-control" name="search" value="{{ request('search') }}">
						</div>
					</div>
					<div class="col-md-2">
						<div class="form-group">
							<label class="control-label">{{ _lang('Gender') }}</label>
							<select class="form-control select2" name="gender" data-selected="{{ request('gender') }}">
								<option value="">{{ _lang('All') }}</option>
								<option value="male">{{ _lang('Male') }}</option>
								<option value="female">{{ _lang('Female') }}</option>
								<option value="other">{{ _lang('Other') }}</option>
							</select>
						</div>
					</div>
					<div class="col-md-2">
						<div class="form-group">
							<label class="control-label">{{ _lang('Status') }}</label>
							<select class="form-control select2" name="filter_status" data-selected="{{ request('filter_status') }}">
								<option value="">{{ _lang('All') }}</option>
								<option value="1">{{ _lang('Active') }}</option>
								<option value="0">{{ _lang('In-Active') }}</option>
								<option value="4">{{ _lang('Banned') }}</option>
							</select>
						</div>
					</div>
					<div class="col-md-2">
						<div class="form-group">
							<label class="control-label">{{ _lang('Verified') }}</label>
							<select class="form-control select2" name="verified" data-selected="{{ request('verified') }}">
								<option value="">{{ _lang('All') }}</option>
								<option value="1">{{ _lang('Verified') }}</option>
								<option value="0">{{ _lang('Not Verified') }}</option>
							</select>
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group">
							<label class="control-label">{{ _lang('Country') }}</label>
							<input type="text" class="form-control" name="country" value="{{ request('country') }}" placeholder="e.g. NG, US, GB">
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group">
							<label class="control-label">{{ _lang('Subscription') }}</label>
							<select class="form-control select2" name="subscription" data-selected="{{ request('subscription') }}">
								<option value="">{{ _lang('All') }}</option>
								<option value="free">{{ _lang('Free') }}</option>
								<option value="subscribed">{{ _lang('Subscribed') }}</option>
								<option value="vip">{{ _lang('VIP') }}</option>
							</select>
						</div>
					</div>
					<div class="col-md-2">
						<div class="form-group">
							<label class="control-label">{{ _lang('Min Age') }}</label>
							<input type="number" class="form-control" name="age_min" value="{{ request('age_min') }}" min="18" max="100" placeholder="18">
						</div>
					</div>
					<div class="col-md-2">
						<div class="form-group">
							<label class="control-label">{{ _lang('Max Age') }}</label>
							<input type="number" class="form-control" name="age_max" value="{{ request('age_max') }}" min="18" max="100" placeholder="100">
						</div>
					</div>
					<div class="col-md-2">
						<div class="form-group mt-4">
							<a href="{{ url('users') }}" class="btn btn-info btn-sm" id="btn-refresh">{{ _lang('Refresh') }}</a>
							<button type="submit" class="btn btn-primary btn-sm">{{ _lang('Filter') }}</button>
						</div>
					</div>
				</div>
				</form>
			</div>
		</div>
	</div>
	<div class="col-md-12">
		<div class="card">
			<div class="card-body p-0 p-md-3">
				<div class="table-responsive">
				<table class="table table-bordered mb-0" id="data-table1">
					<thead>
						<tr>

							<th>{{ _lang('Image') }}</th>
        					<th>{{ _lang('Name') }}</th>
        					<th>{{ _lang('Email') }}</th>
        					<th>{{ _lang('Country') }}</th>
        					<th>{{ _lang('Plan') }}</th>
        					<th>{{ _lang('Verified') }}</th>
        					<th>{{ _lang('Joined') }}</th>
        					<th class="text-center">{{ _lang('Status') }}</th>

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
	$('#data-table1').DataTable({
		processing: true,
		serverSide: true,
		ajax: {
			url: _url + "/users",
			data: function(d) {
				d.search_by = $('select[name="search_by"]').val();
				d.search = $('input[name="search"]').val();
				d.gender = $('select[name="gender"]').val();
				d.filter_status = $('select[name="filter_status"]').val();
				d.verified = $('select[name="verified"]').val();
				d.country = $('input[name="country"]').val();
				d.subscription = $('select[name="subscription"]').val();
				d.age_min = $('input[name="age_min"]').val();
				d.age_max = $('input[name="age_max"]').val();
			}
		},
		"columns" : [
			{ data : "image", name : "image", className : "image" },
        	{ data : "name", name : "name", className : "name" },
        	{ data : "email", name : "email", className : "email" },
        	{ data : "country", name : "country", className : "country" },
        	{ data : "plan", name : "plan", className : "text-center" },
        	{ data : "is_verified", name : "is_verified", className : "text-center" },
        	{ data : "created_at", name : "created_at", className : "created_at" },
        	{ data : "status", name : "status", className : "status text-center" },
			{ data : "action", name : "action", orderable : false, searchable : false, className : "text-center" }
		],
		responsive: true,
		"bStateSave": true,
		"bAutoWidth":false,
		"ordering": false
	});

	// Reload table when filter form is submitted
	$('#user-filter-form').on('submit', function(e) {
		e.preventDefault();
		$('#data-table1').DataTable().ajax.reload();
	});

	// Reload table when refresh button is clicked
	$('#btn-refresh').on('click', function(e) {
		e.preventDefault();
		$('#user-filter-form')[0].reset();
		$('#user-filter-form select').val('').trigger('change');
		$('#data-table1').DataTable().ajax.reload();
	});
</script>
@endsection