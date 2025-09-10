<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('faqs.store') }}" enctype="multipart/form-data">
	@csrf
	<div class="row">
		
		<div class="col-md-12">
			<div class="form-group">
				<label class="form-control-label">{{ _lang('Question') }}</label>
				<input type="text" name="question" class="form-control" value="{{ old('question') }}" required>
			</div>
		</div>
		<div class="col-md-12">
			<div class="form-group">
				<label class="form-control-label">{{ _lang('Answer') }}</label>
				<textarea name="answer" class="form-control" rows="4" required>{{ old('answer') }}</textarea>
			</div>
		</div>
		<div class="col-md-12">
			<div class="form-group">
				<label class="form-control-label">{{ _lang('Status') }}</label>
				<select class="form-control select2" name="status" data-selected="{{ old("status", 1) }}"  required>
                    <option value="1">{{ _lang("Active") }}</option>
                    <option value="0">{{ _lang("In-Active") }}</option>
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
