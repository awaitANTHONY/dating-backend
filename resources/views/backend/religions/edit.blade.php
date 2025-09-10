@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-md-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Edit Religion') }}</span>
			</div>
			<div class="card-body">
				<form method="post" action="{{ route('religions.update', $religion->id) }}" enctype="multipart/form-data">
					@csrf
					{{ method_field('PUT') }}
					<div class="row">
						
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-control-label">{{ _lang('Title') }}</label>
								<input type="text" name="title" class="form-control" value="{{ $religion->title }}" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-control-label">{{ _lang('Status') }}</label>
								<select class="form-control select2" name="status" data-selected="{{ $religion->status }}" required>
									<option value="1">{{ _lang("Active") }}</option>
									<option value="0">{{ _lang("In-Active") }}</option>
								</select>
							</div>
						</div>

						<div class="col-md-12 mt-3">
							<div class="form-group">
								<button type="reset" class="btn btn-danger btn-sm">{{ _lang('Reset') }}</button>
								<button type="submit" class="btn btn-primary btn-sm">{{ _lang('Save') }}</button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection
