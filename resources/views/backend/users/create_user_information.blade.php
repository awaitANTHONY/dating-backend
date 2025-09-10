@extends('layouts.app')

@section('content')
<h2 class="card-title d-none">{{ _lang('Add User Information') }}</h2>
<div class="row">
    <div class="col-md-10">
        <div class="card">
            <div class="card-body">
                <form method="post" autocomplete="off" action="{{ route('users.store-user-information') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row">

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('User') }} <span class="text-danger">*</span></label>
                                <select class="form-control select2" name="user_id" required>
                                    <option value="">{{ _lang('Select User') }}</option>
                                    {!! create_option('users', 'id', 'name', old('user_id')) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Gender') }} <span class="text-danger">*</span></label>
                                <select class="form-control select2" name="gender" required>
                                    <option value="">{{ _lang('Select Gender') }}</option>
                                    <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>{{ _lang('Male') }}</option>
                                    <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>{{ _lang('Female') }}</option>
                                    <option value="other" {{ old('gender') == 'other' ? 'selected' : '' }}>{{ _lang('Other') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Date of Birth') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_of_birth" value="{{ old('date_of_birth') }}" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Religion') }}</label>
                                <select class="form-control select2" name="religion_id">
                                    <option value="">{{ _lang('Select Religion') }}</option>
                                    {!! create_option('religions', 'id', 'name', old('religion_id')) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Bio') }}</label>
                                <textarea class="form-control" name="bio" rows="4" placeholder="{{ _lang('Tell us about yourself...') }}">{{ old('bio') }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Latitude') }}</label>
                                <input type="number" step="any" class="form-control" name="latitude" value="{{ old('latitude') }}" placeholder="0.000000">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Longitude') }}</label>
                                <input type="number" step="any" class="form-control" name="longitude" value="{{ old('longitude') }}" placeholder="0.000000">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Search Preference') }} <span class="text-danger">*</span></label>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="search_preference[]" value="male" id="search_male" {{ in_array('male', old('search_preference', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="search_male">
                                                {{ _lang('Male') }}
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="search_preference[]" value="female" id="search_female" {{ in_array('female', old('search_preference', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="search_female">
                                                {{ _lang('Female') }}
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="search_preference[]" value="other" id="search_other" {{ in_array('other', old('search_preference', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="search_other">
                                                {{ _lang('Other') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Relation Goals') }}</label>
                                <select class="form-control select2" name="relation_goals[]" multiple>
                                    {!! create_option('relation_goals', 'id', 'name', '', null) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Interests') }}</label>
                                <select class="form-control select2" name="interests[]" multiple>
                                    {!! create_option('interests', 'id', 'name', '', null) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Languages') }}</label>
                                <select class="form-control select2" name="languages[]" multiple>
                                    {!! create_option('languages', 'id', 'name', '', null) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Wallet Balance') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="wallet_balance" value="{{ old('wallet_balance', 0.00) }}" placeholder="0.00">
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
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    // Initialize Select2 for multiple select
    $('.select2').select2({
        placeholder: function(){
            return $(this).data('placeholder');
        }
    });
    
    // Custom validation for search preferences
    $('form').on('submit', function(e) {
        var searchPreferences = $('input[name="search_preference[]"]:checked').length;
        if (searchPreferences === 0) {
            e.preventDefault();
            alert('{{ _lang("Please select at least one search preference.") }}');
            return false;
        }
    });
});
</script>
@endsection
