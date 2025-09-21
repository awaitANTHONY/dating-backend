
@extends('layouts.app')

@section('content')
<h2 class="card-title d-none">{{ _lang('Edit') }}</h2>
<form method="post" autocomplete="off" action="{{ route('users.update', $user->id) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
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
                                <label class="control-label">{{ _lang('Name') }}</label>
                                <input type="text" class="form-control" name="name" value="{{ $user->name }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Email') }}</label>
                                <input type="email" class="form-control" name="email" value="{{ $user->email }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Country Code') }}</label>
                                <select class="form-control select2" name="country_code" data-selected="{{ optional($user->user_information)->country_code }}">
                                    <option value="">{{ _lang('Select One') }}</option>
                                    {!! get_country_codes() !!}
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Phone') }}</label>
                                <input type="text" class="form-control" name="phone" value="{{ optional($user->user_information)->phone }}" placeholder="e.g. 5551234567">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Status') }}</label>
                                <select class="form-control select2" name="status" data-selected="{{ $user->status }}" required>
                                    <option value="1">{{ _lang('Active') }}</option>
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
                                <label class="control-label">{{ _lang('Gender') }}</label>
                                <select class="form-control select2" name="gender" data-selected="{{ optional($user->user_information)->gender }}" required>
                                    <option value="">{{ _lang('Select Gender') }}</option>
                                    <option value="male">{{ _lang('Male') }}</option>
                                    <option value="female">{{ _lang('Female') }}</option>
                                    <option value="other">{{ _lang('Other') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Date of Birth') }}</label>
                                <input type="text" class="form-control datepicker" name="date_of_birth" value="{{ optional($user->user_information)->date_of_birth }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Religion') }}</label>
                                <select class="form-control select2" name="religion_id" data-selected="{{ optional($user->user_information)->religion_id }}">
                                    <option value="">{{ _lang('Select Religion') }}</option>
                                    {!! create_option('religions', 'id', 'title') !!}
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Wallet Balance') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="wallet_balance" value="{{ optional($user->user_information)->wallet_balance ?? 0.00 }}" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Bio') }}</label>
                                <textarea class="form-control" name="bio" rows="4" placeholder="{{ _lang('Tell us about yourself...') }}">{{ optional($user->user_information)->bio }}</textarea>
                            </div>
                        </div>
                        <!-- Location Information -->
                        <div class="col-md-12 mt-4">
                            <h5 class="mb-3">{{ _lang('Location Information') }}</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Latitude') }}</label>
                                <input type="number" step="any" class="form-control" name="latitude" value="{{ optional($user->user_information)->latitude }}" placeholder="0.000000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Longitude') }}</label>
                                <input type="number" step="any" class="form-control" name="longitude" value="{{ optional($user->user_information)->longitude }}" placeholder="0.000000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Search Radius (km)') }}</label>
                                <input type="number" step="0.1" min="0" class="form-control" name="search_radius" value="{{ optional($user->user_information)->search_radius ?? 1.0 }}" placeholder="1.0">
                            </div>
                        </div>
                        <!-- Preferences Information -->
                        <div class="col-md-12 mt-4">
                            <h5 class="mb-3">{{ _lang('Preferences & Interests') }}</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Search Preference') }}</label>
                                <select class="form-control select2" name="search_preference" data-selected="{{ optional($user->user_information)->search_preference }}" required>
                                    <option value="male">{{ _lang('Male') }}</option>
                                    <option value="female">{{ _lang('Female') }}</option>
                                    <option value="both">{{ _lang('Both') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Relation Goals') }}</label>
                                <select class="form-control select2-multi" name="relation_goals[]" data-selected="{{ optional($user->user_information)->relation_goals }}" multiple>
                                    {!! create_option('relation_goals', 'id', 'title', '', null) !!}
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Interests') }}</label>
                                <select class="form-control select2-multi" name="interests[]" data-selected="{{ optional($user->user_information)->interests }}" multiple>
                                    {!! create_option('interests', 'id', 'title', '', null) !!}
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Languages') }}</label>
                                <select class="form-control select2-multi" name="languages[]" data-selected="{{ optional($user->user_information)->languages }}" multiple>
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
                        <div class="col-md-12">
                            <h5 class="mb-3">{{ _lang('Images') }}</h5>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Profile') }}</label>
                                <input type="file" class="form-control dropify" name="image" data-allowed-file-extensions="png jpg jpeg PNG JPG JPEG" data-default-file="{{ asset($user->image) }}">
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
