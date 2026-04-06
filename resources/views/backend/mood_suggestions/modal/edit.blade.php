<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('mood_suggestions.update', $mood->id) }}">
    @csrf
    {{ method_field('PUT') }}
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Mood Text') }}</label>
                <input type="text" name="text" class="form-control" value="{{ $mood->text }}" maxlength="50" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Sort Order') }}</label>
                <input type="number" name="sort_order" class="form-control" value="{{ $mood->sort_order }}" min="0">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label class="form-control-label">{{ _lang('Status') }}</label>
                <select class="form-control select2" name="is_active" data-selected="{{ $mood->is_active }}" required>
                    <option value="1">{{ _lang('Active') }}</option>
                    <option value="0">{{ _lang('In-Active') }}</option>
                </select>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                <button type="reset" class="btn btn-danger btn-sm">{{ _lang('Reset') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ _lang('Update') }}</button>
            </div>
        </div>
    </div>
</form>
