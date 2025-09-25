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
                                <select class="form-control select2" name="gender" data-selected="{{ old('gender') }}" required>
                                    <option value="">{{ _lang('Select Gender') }}</option>
                                    <option value="male">{{ _lang('Male') }}</option>
                                    <option value="female">{{ _lang('Female') }}</option>
                                  
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

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Age') }}</label>
                                <input type="number" min="18" max="100" class="form-control" name="age" value="{{ old('age') }}" placeholder="18">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Relationship Status') }}</label>
                                <select class="form-control select2" name="relationship_status_id">
                                    <option value="">{{ _lang('Select Relationship Status') }}</option>
                                    {!! create_option('relationship_statuses', 'id', 'title', old('relationship_status_id')) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Ethnicity') }}</label>
                                <select class="form-control select2" name="ethnicity_id">
                                    <option value="">{{ _lang('Select Ethnicity') }}</option>
                                    {!! create_option('ethnicities', 'id', 'title', old('ethnicity_id')) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Education') }}</label>
                                <select class="form-control select2" name="education_id">
                                    <option value="">{{ _lang('Select Education') }}</option>
                                    {!! create_option('educations', 'id', 'title', old('education_id')) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Career Field') }}</label>
                                <select class="form-control select2" name="carrer_field_id">
                                    <option value="">{{ _lang('Select Career Field') }}</option>
                                    {!! create_option('career_fields', 'id', 'title', old('carrer_field_id')) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Alcohol') }}</label>
                                <select class="form-control select2" name="alkohol" data-selected="{{ old('alkohol') }}">
                                    <option value="">{{ _lang('Select Alcohol Preference') }}</option>
                                    <option value="dont_drink">{{ _lang('Don\'t Drink') }}</option>
                                    <option value="drink_socially">{{ _lang('Drink Socially') }}</option>
                                    <option value="drink_frequently">{{ _lang('Drink Frequently') }}</option>
                                    <option value="prefer_not_to_say">{{ _lang('Prefer Not to Say') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Smoking') }}</label>
                                <select class="form-control select2" name="smoke" data-selected="{{ old('smoke') }}">
                                    <option value="">{{ _lang('Select Smoking Preference') }}</option>
                                    <option value="dont_smoke">{{ _lang('Don\'t Smoke') }}</option>
                                    <option value="smoke_occasionally">{{ _lang('Smoke Occasionally') }}</option>
                                    <option value="smoke_regularly">{{ _lang('Smoke Regularly') }}</option>
                                    <option value="prefer_not_to_say">{{ _lang('Prefer Not to Say') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Height (cm)') }}</label>
                                <input type="number" min="100" max="300" class="form-control" name="height" value="{{ old('height') }}" placeholder="170">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Preferred Age Range') }}</label>
                                <input type="text" class="form-control" name="preffered_age" value="{{ old('preffered_age') }}" placeholder="25-35">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="hidden" name="is_zodiac_sign_matter" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_zodiac_sign_matter" value="1" id="zodiac_sign_matter" {{ old('is_zodiac_sign_matter') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="zodiac_sign_matter">
                                        {{ _lang('Zodiac Sign Compatibility Matters') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="hidden" name="is_food_preference_matter" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_food_preference_matter" value="1" id="food_preference_matter" {{ old('is_food_preference_matter') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="food_preference_matter">
                                        {{ _lang('Food Preference Compatibility Matters') }}
                                    </label>
                                </div>
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
