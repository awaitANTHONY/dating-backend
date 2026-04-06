<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('contact_platforms.store') }}">
    @csrf
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Name') }}</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. WhatsApp" required>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Icon URL') }}</label>
                <input type="text" name="icon" class="form-control" value="{{ old('icon') }}" placeholder="e.g. images/platforms/whatsapp.png">
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Placeholder Text') }}</label>
                <input type="text" name="placeholder" class="form-control" value="{{ old('placeholder') }}" placeholder="e.g. Enter your WhatsApp number">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Sort Order') }}</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Status') }}</label>
                <select class="form-control select2" name="status" data-selected="{{ old('status', 1) }}" required>
                    <option value="1">{{ _lang('Active') }}</option>
                    <option value="0">{{ _lang('In-Active') }}</option>
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
