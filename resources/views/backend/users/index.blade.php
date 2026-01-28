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
				<form method="get" autocomplete="off" action="{{ url('users') }}">
       
				<div class="row">
					<div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Search by') }}</label>
                                <select class="form-control select2" name="search_by" data-selected="{{ request('search_by') }}" required>
                                    <option value="">{{ _lang('Select One') }}</option>
                                    <option value="name">{{ _lang('Name') }}</option>
									<option value="email">{{ _lang('Email') }}</option>
									<option value="status">{{ _lang('Status') }}</option>

                                </select>
                            </div>
                    </div>
					<div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Search') }} </label>
                                <input type="text" class="form-control" name="search" value="{{ request('search') }}" required>
                            </div>
                        </div>
					<div class="col-md-12 ">
                            <div class="form-group margin-auto">
								<a href="{{ url('users') }}" class="btn btn-info btn-sm">{{ _lang('Refresh') }}</a>
                                <button type="submit" class="btn btn-primary btn-sm">{{ _lang('Save') }}</button>
                            </div>
                        </div>
				</div>
				
				</form>
			</div>
		</div>
	</div>
	<div class="col-md-12">
		<div class="card">
			<div class="card-body">
				<table class="table table-bordered" id="data-table1">
					<thead>
						<tr>
							
							<th>{{ _lang('Image') }}</th>
        					<th>{{ _lang('Name') }}</th>
        					<th>{{ _lang('Email') }}</th>
        					<th class="text-center">{{ _lang('Status') }}</th>

							<th class="text-center">{{ _lang('Action') }}</th>
						</tr>
					</thead>
				</table>
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
			}
		},
		"columns" : [
			
			{ data : "image", name : "image", className : "image" },
        	{ data : "name", name : "name", className : "name" },
        	{ data : "email", name : "email", className : "email" },
        	{ data : "status", name : "status", className : "status text-center" },
			{ data : "action", name : "action", orderable : false, searchable : false, className : "text-center" }
			
		],
		responsive: true,
		"bStateSave": true,
		"bAutoWidth":false,	
		"ordering": false
	});

	// Reload table when search button is clicked
	$('form').on('submit', function(e) {
		e.preventDefault();
		$('#data-table1').DataTable().ajax.reload();
	});

	// Reload table when refresh button is clicked
	$('a.btn-info').on('click', function(e) {
		e.preventDefault();
		$('select[name="search_by"]').val('').trigger('change');
		$('input[name="search"]').val('');
		$('#data-table1').DataTable().ajax.reload();
	});
</script>
@endsection