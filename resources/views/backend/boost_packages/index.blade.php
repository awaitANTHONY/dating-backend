@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-md-6 breadcrumb-box"></div>
	<div class="col-md-6 mb-2 text-right">
		<h2 class="card-title d-none">{{ _lang('Boost Packages List') }}</h2>
		<a class="btn btn-primary btn-sm" href="{{ route('boost-packages.create') }}" >
			<i class="fas fa-plus mr-1"></i>
			{{ _lang('Add New') }}
		</a>
	</div>

	<div class="col-md-12">
		<div class="card">
			<div class="card-body">
				<table class="table table-bordered" id="data-table">
					<thead>
						<tr>
							
        					<th>{{ _lang('Name') }}</th>
        					<th>{{ _lang('Boost Count') }}</th>
        					<th>{{ _lang('Platform') }}</th>
        					<th>{{ _lang('Status') }}</th>

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
<script type="text/javascript">

	$('#data-table').DataTable({
		processing: true,
		serverSide: true,
		ajax: _url + "/boost-packages",
		"columns" : [
			
        	{ data : "name", name : "name", className : "name" },
        	{ data : "boost_count", name : "boost_count", className : "boost_count" },
        	{ data : "platform", name : "platform", className : "platform" },
        	{ data : "status", name : "status", className : "status text-center" },
					{ data : "action", name : "action", orderable : false, searchable : false, className : "text-center" }
			
		],
		responsive: true,
		"bStateSave": true,
		"bAutoWidth":false,	
		"ordering": false
	});

</script>
<script src="https://code.jquery.com/ui/1.13.1/jquery-ui.js"></script>
<script type="text/javascript">
	$(function() {
      $("#data-table tbody").sortable({
          update: function(event, ui)
            {
                var packages = [];
                var packageOrder = 1;
                $("#data-table tbody > tr").each(function(){
                    var id = $(this).data('id');
                    packages.push( { id: id, position: packageOrder });
                    packageOrder++;
                });
                
                var packages = JSON.stringify( packages );

				console.log(packages);

                $.ajax({
                    method: "POST",
                    url: '{{ url("boost-packages/reorder") }}',
                    data:  { _token: $('[name=csrf-token]').attr('content'), packages},
                    cache: false,
                    success: function(data){
                       
                        toast('success', data['message']);
                        
                    }
                });
            }
      	});
    });
</script>
@endsection