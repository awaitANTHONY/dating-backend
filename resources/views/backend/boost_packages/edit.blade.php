@extends('layouts.app')

@section('content')
    <h2 class="card-title d-none">{{ _lang('Edit Boost Package') }}</h2>
    <form class="ajax-submit2" method="post" autocomplete="off" action="{{ route('boost-packages.update', $boostPackage->id) }}"
        enctype="multipart/form-data">
        @csrf
        @method('PUT')
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
                                    <input type="text" class="form-control" name="name" 
                                        value="{{ $boostPackage->name }}" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Description') }}</label>
                                    <textarea class="form-control" name="description" rows="3">{{ $boostPackage->description }}</textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Boost Duration (Minutes)') }}</label>
                                    <input type="number" class="form-control" name="boost_duration"
                                        value="{{ $boostPackage->boost_duration }}" min="15" max="240" required>
                                    <small class="form-text text-muted">Duration of each boost in minutes (15-240)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Platform') }}</label>
                                    <select class="form-control select2" name="platform"
                                        data-selected="{{ $boostPackage->platform }}" required>
                                        <option value="ios">{{ _lang('iOS') }}</option>
                                        <option value="android">{{ _lang('Android') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Product ID') }}</label>
                                    <input type="text" class="form-control" name="product_id" 
                                        value="{{ $boostPackage->product_id }}" required>
                                    <small class="form-text text-muted">Unique identifier for app store purchases</small>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Status') }}</label>
                                    <select class="form-control select2" name="status" 
                                        data-selected="{{ $boostPackage->status }}" required>
                                        <option value="1">{{ _lang('Active') }}</option>
                                        <option value="0">{{ _lang('Inactive') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group">
                                    <button type="reset" class="btn btn-danger btn-sm">{{ _lang('Reset') }}</button>
                                    <button type="submit" class="btn btn-primary btn-sm">{{ _lang('Update Package') }}</button>
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
                                <h2 class="b">Package Stats</h2>
                                
                                <div class="alert alert-primary">
                                    <h6><i class="fas fa-chart-bar"></i> Current Package</h6>
                                    <p><strong>{{ $boostPackage->name }}</strong></p>
                                    <ul class="mb-0">
                                        <li><strong>Boost Count:</strong> {{ $boostPackage->boost_count }}</li>
                                        <li><strong>Platform:</strong> {{ ucfirst($boostPackage->platform) }}</li>
                                        <li><strong>Status:</strong> 
                                            @if($boostPackage->status)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-danger">Inactive</span>
                                            @endif
                                        </li>
                                    </ul>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-rocket"></i> Boost Features</h6>
                                    <ul class="mb-0">
                                        <li><i class="fas fa-check text-success"></i> Top position in recommendations</li>
                                        <li><i class="fas fa-check text-success"></i> 30 minutes duration per boost</li>
                                        <li><i class="fas fa-check text-success"></i> Increased visibility and matches</li>
                                    </ul>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Important</h6>
                                    <p class="mb-0">Changes to Product ID require app store configuration updates.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection