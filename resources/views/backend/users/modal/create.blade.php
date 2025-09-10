<form method="post" class="ajax-submit slimscroll" autocomplete="off" action="{{ route('users.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="row">
        <!-- Basic User Information -->
        <div class="col-md-12">
            <h6 class="mb-3">{{ _lang('Basic Information') }}</h6>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label class="control-label">{{ _lang('Name') }} <span class="text-danger">*</span></label>
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
                <label class="control-label">{{ _lang('Password') }} <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password" required>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label class="control-label">{{ _lang('Confirm Password') }} <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password_confirmation" required>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label class="control-label">{{ _lang('Status') }} <span class="text-danger">*</span></label>
                <select class="form-control select2" name="status" required>
                    <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>{{ _lang('Active') }}</option>
                    <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>{{ _lang('In-Active') }}</option>
                </select>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label class="control-label">{{ _lang('Image') }}</label>
                <input type="file" class="form-control dropify" name="image" data-allowed-file-extensions="png jpg jpeg PNG JPG JPEG">
            </div>
        </div>

        <!-- User Profile Information -->
        <div class="col-md-12 mt-3">
            <h6 class="mb-3">{{ _lang('Profile Information') }}</h6>
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

        <div class="col-md-6">
            <div class="form-group">
                <label class="control-label">{{ _lang('Wallet Balance') }}</label>
                <input type="number" step="0.01" min="0" class="form-control" name="wallet_balance" value="{{ old('wallet_balance', 0.00) }}" placeholder="0.00">
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label class="control-label">{{ _lang('Bio') }}</label>
                <textarea class="form-control" name="bio" rows="3" placeholder="{{ _lang('Tell us about yourself...') }}">{{ old('bio') }}</textarea>
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
                            <input class="form-check-input" type="checkbox" name="search_preference[]" value="male" id="modal_search_male" {{ in_array('male', old('search_preference', [])) ? 'checked' : '' }}>
                            <label class="form-check-label" for="modal_search_male">
                                {{ _lang('Male') }}
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="search_preference[]" value="female" id="modal_search_female" {{ in_array('female', old('search_preference', [])) ? 'checked' : '' }}>
                            <label class="form-check-label" for="modal_search_female">
                                {{ _lang('Female') }}
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="search_preference[]" value="other" id="modal_search_other" {{ in_array('other', old('search_preference', [])) ? 'checked' : '' }}>
                            <label class="form-check-label" for="modal_search_other">
                                {{ _lang('Other') }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label class="control-label">{{ _lang('Relation Goals') }}</label>
                <select class="form-control select2" name="relation_goals[]" multiple>
                    {!! create_option('relation_goals', 'id', 'name', '', null) !!}
                </select>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label class="control-label">{{ _lang('Interests') }}</label>
                <select class="form-control select2" name="interests[]" multiple>
                    {!! create_option('interests', 'id', 'title', '', null) !!}
                </select>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label class="control-label">{{ _lang('Languages') }}</label>
                <select class="form-control select2" name="languages[]" multiple>
                    {!! create_option('languages', 'id', 'name', '', null) !!}
                </select>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-sm">{{ _lang('Save') }}</button>
            </div>
        </div>
    </div>
</form>

<script>
$(document).ready(function() {
    // Initialize Select2 for multiple select in modal
    $('.select2').select2({
        dropdownParent: $('.modal'),
        placeholder: function(){
            return $(this).data('placeholder');
        }
    });
});
</script>

