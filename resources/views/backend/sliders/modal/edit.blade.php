<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('sliders.update', $slider->id) }}" enctype="multipart/form-data">
	@csrf
	@method('PUT')
	<div class="row">
		
		<div class="col-md-12">
			<div class="form-group">
				<label class="form-control-label">{{ _lang('Title') }}</label>
				<input type="text" name="title" class="form-control" value="{{ $slider->title }}" required>
			</div>
		</div>
		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Image') }}</label>
				<input type="file" class="form-control dropify" name="image" data-allowed-file-extensions="png jpg jpeg PNG JPG JPEG" data-default-file="{{ asset($slider->image) }}">
			</div>
		</div>
		<div class="col-md-12">
			<div class="form-group">
				<label class="form-control-label">{{ _lang('Action Url') }}</label>
				<input type="text" name="action_url" class="form-control" value="{{ $slider->action_url }}">
			</div>
		</div>
		<div class="col-md-12">
			<div class="form-group">
				<label class="form-control-label">{{ _lang('Status') }}</label>
				<select class="form-control select2" name="status" data-selected="{{ $slider->status }}"  required>
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



