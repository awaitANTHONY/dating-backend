@extends('layouts.app')

@section('content')
    <h2 class="card-title d-none">{{ _lang('Add New Boost Package') }}</h2>
    <form class="ajax-submit2" method="post" autocomplete="off" action="{{ route('boost-packages.store') }}"
        enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <h2 class="b">Package Information</h2>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Package Name') }}</label>
                                    <input type="text" class="form-control" name="name" value="{{ old('name') }}"
                                        placeholder="e.g., Small Boost, Medium Boost" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Description') }}</label>
                                    <textarea class="form-control" name="description" rows="3" 
                                        placeholder="Brief description of the boost package">{{ old('description') }}</textarea>
                                </div>
                            </div>
                           
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Boost Duration (Minutes)') }}</label>
                                    <input type="number" class="form-control" name="boost_duration"
                                        value="{{ old('boost_duration', 30) }}" min="15" max="240" required>
                                    <small class="form-text text-muted">Duration of each boost in minutes (15-240)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Platform') }}</label>
                                    <select class="form-control select2" name="platform"
                                        data-selected="{{ old('platform', '') }}" required>
                                        <option value="">{{ _lang('Select One') }}</option>
                                        <option value="ios">{{ _lang('iOS') }}</option>
                                        <option value="android">{{ _lang('Android') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Product ID') }}</label>
                                    <input type="text" class="form-control" name="product_id" value="{{ old('product_id') }}" 
                                        placeholder="e.g., dating_app_boost_3" required>
                                    <small class="form-text text-muted">Unique identifier for app store purchases</small>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Status') }}</label>
                                    <select class="form-control select2" name="status" 
                                        data-selected="{{ old('status', 1) }}" required>
                                        <option value="1">{{ _lang('Active') }}</option>
                                        <option value="0">{{ _lang('Inactive') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group">
                                    <button type="reset" class="btn btn-danger btn-sm">{{ _lang('Reset') }}</button>
                                    <button type="submit" class="btn btn-primary btn-sm">{{ _lang('Save Package') }}</button>
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
                                <h2 class="b">Package Preview</h2>
                                <div class="alert alert-info">
                                    <h5><i class="fas fa-rocket"></i> Boost Package</h5>
                                    <p><strong>Features:</strong></p>
                                    <ul>
                                        <li><i class="fas fa-check text-success"></i> Profile appears at top of recommendations</li>
                                        <li><i class="fas fa-check text-success"></i> 30 minutes visibility boost per activation</li>
                                        <li><i class="fas fa-check text-success"></i> Increased profile views and matches</li>
                                        <li><i class="fas fa-check text-success"></i> Can be activated when desired</li>
                                    </ul>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-info-circle"></i> Important Notes</h6>
                                    <ul class="mb-0">
                                        <li>Users can only have one active boost at a time</li>
                                        <li>Each boost lasts exactly 30 minutes</li>
                                        <li>Boosts can be saved and activated later</li>
                                        <li>Product ID must match app store configuration</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection