<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('bio_templates.store') }}">
    @csrf
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Bio Text') }}</label>
                <textarea name="text" class="form-control" rows="3" maxlength="500" required placeholder="{{ _lang('e.g. Living my best life in {city}') }}">{{ old('text') }}</textarea>
                <small class="form-text text-muted">{{ _lang('Use {city} as placeholder for user\'s city. Max 500 characters.') }}</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Gender') }}</label>
                <select class="form-control select2" name="gender" data-selected="{{ old('gender', '') }}">
                    <option value="">{{ _lang('All') }}</option>
                    <option value="male">{{ _lang('Male') }}</option>
                    <option value="female">{{ _lang('Female') }}</option>
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Sort Order') }}</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Status') }}</label>
                <select class="form-control select2" name="is_active" data-selected="{{ old('is_active', 1) }}" required>
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
