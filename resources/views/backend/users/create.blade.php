                        
@extends('layouts.app')

@section('content')
<h2 class="card-title d-none">{{ _lang('Add New') }}</h2>
<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('users.store') }}" enctype="multipart/form-data">
                    @csrf
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                
                    <div class="row">
                        <!-- Basic User Information -->
                        <div class="col-md-12">
                            <h5 class="mb-3">{{ _lang('Basic Information') }}</h5>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Name') }} </label>
                                <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Email') }}</label>
                                <input type="email" class="form-control" name="email" value="{{ old('email') }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Country Code') }}</label>
                                <select class="form-control select2" name="country_code" data-selected="{{ old('country_code') }}">
                                    <option value="">{{ _lang('Select One') }}</option>
                                    {!! get_country_codes() !!}
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Phone') }}</label>
                                <input type="text" class="form-control" name="phone" value="{{ old('phone') }}" placeholder="e.g. 5551234567">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Password') }} </label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Status') }} </label>
                                <select class="form-control select2" name="status" data-selected="{{ old('status', 1) }}" required>
                                    <option value="1" >{{ _lang('Active') }}</option>
                                    <option value="0">{{ _lang('In-Active') }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- User Profile Information -->
                        <div class="col-md-12 mt-4">
                            <h5 class="mb-3">{{ _lang('Profile Information') }}</h5>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Gender') }} </label>
                                <select class="form-control select2" name="gender" data-selected="{{ old('gender') }}" required>
                                    <option value="">{{ _lang('Select Gender') }}</option>
                                    <option value="male">{{ _lang('Male') }}</option>
                                    <option value="female">{{ _lang('Female') }}</option>
                                    <option value="other">{{ _lang('Other') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Date of Birth') }} </label>
                                <input type="text" class="form-control datepicker" name="date_of_birth" value="{{ old('date_of_birth') }}" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Religion') }}</label>
                                <select class="form-control select2" name="religion_id" data-selected="{{ old('religion_id') }}">
                                    <option value="">{{ _lang('Select Religion') }}</option>
                                    {!! create_option('religions', 'id', 'title') !!}
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
                                <select class="form-control select2" name="relationship_status_id" data-selected="{{ old('relationship_status_id') }}">
                                    <option value="">{{ _lang('Select Relationship Status') }}</option>
                                    {!! create_option('relationship_statuses', 'id', 'title') !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Ethnicity') }}</label>
                                <select class="form-control select2" name="ethnicity_id" data-selected="{{ old('ethnicity_id') }}">
                                    <option value="">{{ _lang('Select Ethnicity') }}</option>
                                    {!! create_option('ethnicities', 'id', 'title') !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Education') }}</label>
                                <select class="form-control select2" name="education_id" data-selected="{{ old('education_id') }}">
                                    <option value="">{{ _lang('Select Education') }}</option>
                                    {!! create_option('educations', 'id', 'title') !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Career Field') }}</label>
                                <select class="form-control select2" name="carrer_field_id" data-selected="{{ old('carrer_field_id') }}">
                                    <option value="">{{ _lang('Select Career Field') }}</option>
                                    {!! create_option('career_fields', 'id', 'title') !!}
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

                        <!-- Location Information -->
                        <div class="col-md-12 mt-4">
                            <h5 class="mb-3">{{ _lang('Location Information') }}</h5>
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
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Search Radius (km)') }}</label>
                                <input type="number" step="0.1" min="0" class="form-control" name="search_radius" value="{{ old('search_radius', 1.0) }}" placeholder="1.0">
                            </div>
                        </div>

                        <!-- Preferences Information -->
                        <div class="col-md-12 mt-4">
                            <h5 class="mb-3">{{ _lang('Preferences & Interests') }}</h5>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Search Preference') }} </label>
                                <select class="form-control select2" name="search_preference" data-selected="{{ old('search_preference') }}" required>
                                    <option value="male">{{ _lang('Male') }}</option>
                                    <option value="female">{{ _lang('Female') }}</option>
                                    <option value="both">{{ _lang('Both') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Relation Goals') }}</label>
                                <select class="form-control select2-multi" name="relation_goals[]" data-selected="{{ old('relation_goals') ? implode(',', old('relation_goals')) : '' }}" multiple>
                                    {!! create_option('relation_goals', 'id', 'title', '', null) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Interests') }}</label>
                                <select class="form-control select2-multi" name="interests[]" data-selected="{{ old('interests') ? implode(',', old('interests')) : '' }}" multiple>
                                    {!! create_option('interests', 'id', 'title', '', null) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Languages') }}</label>
                                <select class="form-control select2-multi" name="languages[]" data-selected="{{ old('languages') ? implode(',', old('languages')) : '' }}" multiple>
                                    {!! create_option('languages', 'id', 'title', '', null) !!}
                                </select>
                            </div>
                        </div>

                        <div class="col-md-12 ">
                            <div class="form-group margin-auto">
                                <button type="reset" class="btn btn-danger btn-sm">{{ _lang('Reset') }}</button>
                                <button type="submit" class="btn btn-primary btn-sm">{{ _lang('Save') }}</button>
                            </div>
                        </div>
                    </div>
                
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
               
                    <div class="row">
                        <!-- Basic User Information -->
                        <div class="col-md-12">
                            <h5 class="mb-3">{{ _lang('Images') }}</h5>
                        </div>
                        
                         <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Profile') }}</label>
                                <input type="file" class="form-control dropify" name="image" data-allowed-file-extensions="png jpg jpeg PNG JPG JPEG" required>
                            </div>
                        </div>

                         <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Others Images') }}</label>
                                <input type="file" class="form-control dropify" name="images[]" data-allowed-file-extensions="png jpg jpeg PNG JPG JPEG" multiple>
                            </div>
                        </div>

                       
                    </div>
               
            </div>
        </div>
    </div>
</div>
</form>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    
    
    
});
</script>
@endsection