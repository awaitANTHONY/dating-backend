@extends('layouts.app')
@section('content')
<div class="card-title" style="display: none;">{{ _lang('Coin Reward Settings') }}</div>
<div class="row">
    <div class="col-md-3">
        <div class="nav flex-column nav-pills nav-primary nav-pills-no-bd" id="v-pills-tab-without-border" role="tablist" aria-orientation="vertical">
            <a class="nav-link active show" id="rewards-tab" data-toggle="pill" href="#rewards" role="tab">{{ _lang('Reward Amounts') }}</a>
            <a class="nav-link" id="subscriber-tab" data-toggle="pill" href="#subscriber" role="tab">{{ _lang('Subscriber Daily') }}</a>
            <a class="nav-link" id="dc-tab" data-toggle="pill" href="#dc" role="tab">{{ _lang('Direct Connect') }}</a>
        </div>
    </div>
    <div class="col-md-9">
        <div class="tab-content" id="v-pills-without-border-tabContent">

            {{-- Reward Amounts Tab --}}
            <div class="tab-pane fade active show" id="rewards" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3 header-title card-title">{{ _lang('Coin Reward Amounts') }}</h3>
                        <p class="text-muted mb-3">{{ _lang('Set the number of coins users earn for each action.') }}</p>
                        <form method="post" class="ajax-submit2 params-card" autocomplete="off" action="{{ route('store_settings') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Daily Login') }}</label>
                                        <input type="number" class="form-control" name="coin_daily_login" value="{{ get_option('coin_daily_login', '1') }}" min="0" max="100" required>
                                        <small class="form-text text-muted">{{ _lang('Coins for daily login (once per day)') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Follow Social Media') }}</label>
                                        <input type="number" class="form-control" name="coin_follow_social" value="{{ get_option('coin_follow_social', '5') }}" min="0" max="100" required>
                                        <small class="form-text text-muted">{{ _lang('Coins per social follow (one-time each)') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Referral') }}</label>
                                        <input type="number" class="form-control" name="coin_referral" value="{{ get_option('coin_referral', '10') }}" min="0" max="100" required>
                                        <small class="form-text text-muted">{{ _lang('Coins per successful referral') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Complete Profile') }}</label>
                                        <input type="number" class="form-control" name="coin_complete_profile" value="{{ get_option('coin_complete_profile', '5') }}" min="0" max="100" required>
                                        <small class="form-text text-muted">{{ _lang('Coins for 100% profile completion (one-time)') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group text-right">
                                        <button type="submit" class="btn btn-primary btn-sm">{{ _lang('Update') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Subscriber Daily Coins Tab --}}
            <div class="tab-pane fade" id="subscriber" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3 header-title card-title">{{ _lang('Subscriber Daily Coins') }}</h3>
                        <p class="text-muted mb-3">{{ _lang('Subscribers receive free coins daily based on their tier. Granted automatically at 00:05.') }}</p>
                        <form method="post" class="ajax-submit2 params-card" autocomplete="off" action="{{ route('store_settings') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Premium Daily') }}</label>
                                        <input type="number" class="form-control" name="coin_daily_premium" value="{{ get_option('coin_daily_premium', '2') }}" min="0" max="100" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Gold Daily') }}</label>
                                        <input type="number" class="form-control" name="coin_daily_gold" value="{{ get_option('coin_daily_gold', '5') }}" min="0" max="100" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('VIP Daily') }}</label>
                                        <input type="number" class="form-control" name="coin_daily_vip" value="{{ get_option('coin_daily_vip', '10') }}" min="0" max="100" required>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group text-right">
                                        <button type="submit" class="btn btn-primary btn-sm">{{ _lang('Update') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Direct Connect Settings Tab --}}
            <div class="tab-pane fade" id="dc" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3 header-title card-title">{{ _lang('Direct Connect Settings') }}</h3>
                        <p class="text-muted mb-3">{{ _lang('Configure contact sharing limits, free requests, and coin costs per tier.') }}</p>
                        <form method="post" class="ajax-submit2 params-card" autocomplete="off" action="{{ route('store_settings') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-12 mb-2"><strong>{{ _lang('Contact Limits per Tier') }}</strong></div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Free') }}</label>
                                        <input type="number" class="form-control" name="dc_contact_limit_free" value="{{ get_option('dc_contact_limit_free', '0') }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Premium') }}</label>
                                        <input type="number" class="form-control" name="dc_contact_limit_premium" value="{{ get_option('dc_contact_limit_premium', '2') }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Gold') }}</label>
                                        <input type="number" class="form-control" name="dc_contact_limit_gold" value="{{ get_option('dc_contact_limit_gold', '4') }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('VIP') }}</label>
                                        <input type="number" class="form-control" name="dc_contact_limit_vip" value="{{ get_option('dc_contact_limit_vip', '100') }}" min="0">
                                    </div>
                                </div>

                                <div class="col-md-12 mb-2 mt-2"><strong>{{ _lang('Daily Free Requests per Tier') }}</strong></div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Free') }}</label>
                                        <input type="number" class="form-control" name="dc_free_requests_free" value="{{ get_option('dc_free_requests_free', '0') }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Premium') }}</label>
                                        <input type="number" class="form-control" name="dc_free_requests_premium" value="{{ get_option('dc_free_requests_premium', '3') }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Gold') }}</label>
                                        <input type="number" class="form-control" name="dc_free_requests_gold" value="{{ get_option('dc_free_requests_gold', '5') }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('VIP') }}</label>
                                        <input type="number" class="form-control" name="dc_free_requests_vip" value="{{ get_option('dc_free_requests_vip', '10') }}" min="0">
                                    </div>
                                </div>

                                <div class="col-md-12 mb-2 mt-2"><strong>{{ _lang('Coin Costs') }}</strong></div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Default Cost') }}</label>
                                        <input type="number" class="form-control" name="dc_coin_cost_default" value="{{ get_option('dc_coin_cost_default', '5') }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('VIP Cost') }}</label>
                                        <input type="number" class="form-control" name="dc_coin_cost_vip" value="{{ get_option('dc_coin_cost_vip', '3') }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Request Expiry (Hours)') }}</label>
                                        <input type="number" class="form-control" name="dc_request_expiry_hours" value="{{ get_option('dc_request_expiry_hours', '72') }}" min="1">
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group text-right">
                                        <button type="submit" class="btn btn-primary btn-sm">{{ _lang('Update') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
