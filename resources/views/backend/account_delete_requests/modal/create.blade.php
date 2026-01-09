<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('account-delete-requests.store') }}" enctype="multipart/form-data">
	@csrf
	<div class="row">
		
		<div class="col-md-12">
			<div class="form-group">
				<label class="form-control-label">{{ _lang('Email') }}</label>
				<input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
			</div>
		</div>
		
		<div class="col-md-12">
			<div class="form-group">
				<label class="form-control-label">{{ _lang('Type') }}</label>
				<select class="form-control select2" name="type" data-selected="{{ old('type', '1') }}" required>
                    <option value="1">{{ _lang("Clear Data") }}</option>
                    <option value="2">{{ _lang("Clear Data & Account") }}</option>
                </select>
			</div>
		</div>
		
		<div class="col-md-12">
			<div class="form-group">
				<label class="form-control-label">{{ _lang('Status') }}</label>
				<select class="form-control select2" name="accepted" data-selected="{{ old('accepted', 0) }}" required>
                    <option value="0">{{ _lang("Pending") }}</option>
                    <option value="1">{{ _lang("Accepted") }}</option>
                </select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<button type="reset" class="btn btn-danger btn-sm">{{ _lang('Reset') }}</button>
				<button type="submit" class="btn btn-primary btn-sm">{{ _lang('Save') }}</button>
			</div>
		</div>
    </div>
</form>
