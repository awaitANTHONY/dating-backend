@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-header">
        <h2 class="card-title mb-0">Fake User Generator</h2>
    </div>
    <div class="card-body">
        <form method="post" action="{{ route('fake-user-generator.generate') }}">
            @csrf
            <div class="alert alert-danger">
                You need to upload images to the <b>images/male</b> and <b>images/female</b> folders. Then, the algorithm will select a random image from each folder.
            </div>
            <div class="form-group">
                <label>How many users you want to generate?</label>
                <input type="number" class="form-control" name="count" min="1" max="1000" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="text" class="form-control" name="password" value="123456789">
                <small class="text-danger">Choose the password that will be used for all users, default: 123456789</small>
            </div>
            <div class="form-group">
                <label>Gender</label>
                <select class="form-control" name="gender" required>
                    <option value="">Select One</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
            <div class="form-group">
                <label>Select Preference</label>
                <select class="form-control" name="preference" required>
                    <option value="">Select One</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="both">Both</option>
                </select>
            </div>
            <div class="form-group">
                <label>How many Select Random Interest?</label>
                <input type="number" class="form-control" name="interest_count" min="0">
                <small class="text-danger">Number Depend On Your Total Record In Database Of Interest(<a href="{{ url('interests') }}" target="_blank">Add Interest</a>)</small>
            </div>
            <div class="form-group">
                <label>How many Select Random Language?</label>
                <input type="number" class="form-control" name="language_count" min="0">
                <small class="text-danger">Number Depend On Your Total Record In Database Of Language(<a href="{{ url('languages') }}" target="_blank">Add Language</a>)</small>
            </div>
            <div class="form-group">
                <label>Radius To Generate Random Points (KM)?</label>
                <input type="number" class="form-control" name="radius" min="0">
                <small class="text-danger">To Specified Radius near lat long generate randomly.</small>
            </div>
            <div class="form-group">
                <label>Select Country Code</label>
                <select class="form-control select2" name="country_code">
                    <option value="">Select One</option>
                    {!! get_country_codes() !!}
                </select>
            </div>
            <div class="form-group">
                <label>How many digits should the mobile number consist of?</label>
                <input type="number" class="form-control" name="phone_length" min="4" max="15">
            </div>
            <button type="submit" class="btn btn-primary">Generate Users</button>
        </form>
    </div>
</div>
@endsection
