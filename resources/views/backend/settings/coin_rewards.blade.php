@extends('layouts.app')
@section('content')
<div class="card-title" style="display: none;">{{ _lang('Coin Reward Settings') }}</div>
<div class="row">
    <div class="col-md-3">
        <div class="nav flex-column nav-pills nav-primary nav-pills-no-bd" id="v-pills-tab-without-border" role="tablist" aria-orientation="vertical">
            <a class="nav-link active show" id="rewards-tab" data-toggle="pill" href="#rewards" role="tab">{{ _lang('Reward Amounts') }}</a>
            <a class="nav-link" id="subscriber-tab" data-toggle="pill" href="#subscriber" role="tab">{{ _lang('Subscriber Daily') }}</a>
            <a class="nav-link" id="dc-tab" data-toggle="pill" href="#dc" role="tab">{{ _lang('Direct Connect') }}</a>
            <a class="nav-link" id="approval-tab" data-toggle="pill" href="#approval" role="tab">{{ _lang('Approval Limits') }}</a>
            <a class="nav-link" id="content-tab" data-toggle="pill" href="#content" role="tab">{{ _lang('Content Rules') }}</a>
            <a class="nav-link" id="limits-tab" data-toggle="pill" href="#limits" role="tab">{{ _lang('Free Tier Limits') }}</a>
            <a class="nav-link" id="toggles-tab" data-toggle="pill" href="#toggles" role="tab">{{ _lang('Feature Toggles') }}</a>
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

            {{-- Approval Limits Tab --}}
            <div class="tab-pane fade" id="approval" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3 header-title card-title">{{ _lang('Daily Approval Limits') }}</h3>
                        <p class="text-muted mb-3">{{ _lang('How many contact requests a user can approve per day. Beyond the limit, they spend coins per extra approval.') }}</p>
                        <form method="post" class="ajax-submit2 params-card" autocomplete="off" action="{{ route('store_settings') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Free') }}</label>
                                        <input type="number" class="form-control" name="dc_approval_limit_free" value="{{ get_option('dc_approval_limit_free', '3') }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Premium') }}</label>
                                        <input type="number" class="form-control" name="dc_approval_limit_premium" value="{{ get_option('dc_approval_limit_premium', '10') }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Gold') }}</label>
                                        <input type="number" class="form-control" name="dc_approval_limit_gold" value="{{ get_option('dc_approval_limit_gold', '25') }}" min="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('VIP') }}</label>
                                        <input type="number" class="form-control" name="dc_approval_limit_vip" value="{{ get_option('dc_approval_limit_vip', '999') }}" min="0">
                                        <small class="form-text text-muted">{{ _lang('999 = unlimited') }}</small>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-2 mt-2"><strong>{{ _lang('Extra Approval Cost') }}</strong></div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Coins per Extra Approval') }}</label>
                                        <input type="number" class="form-control" name="dc_approval_coin_cost" value="{{ get_option('dc_approval_coin_cost', '3') }}" min="0">
                                        <small class="form-text text-muted">{{ _lang('Charged when daily limit is exceeded') }}</small>
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

            {{-- Content Rules Tab --}}
            <div class="tab-pane fade" id="content" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3 header-title card-title">{{ _lang('Content Filtering') }}</h3>
                        <p class="text-muted mb-3">{{ _lang('Control what users can share in bios, moods, and other text fields.') }}</p>
                        <form method="post" class="ajax-submit2 params-card" autocomplete="off" action="{{ route('store_settings') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Allow Contact Info in Bio') }}</label>
                                        <select class="form-control" name="allow_contact_in_bio">
                                            <option value="0" {{ get_option('allow_contact_in_bio', '0') == '0' ? 'selected' : '' }}>{{ _lang('No — Block phone numbers & handles') }}</option>
                                            <option value="1" {{ get_option('allow_contact_in_bio', '0') == '1' ? 'selected' : '' }}>{{ _lang('Yes — Allow (testing mode)') }}</option>
                                        </select>
                                        <small class="form-text text-muted">{{ _lang('When enabled, users can put phone numbers, WhatsApp handles, etc. in their bio and mood text. Use temporarily for A/B testing.') }}</small>
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

            {{-- Free Tier Limits Tab --}}
            <div class="tab-pane fade" id="limits" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3 header-title card-title">{{ _lang('Free Tier Limits') }}</h3>
                        <p class="text-muted mb-3">{{ _lang('Control daily limits for free (non-subscriber) users. Changes take effect immediately — no app update needed.') }}</p>
                        <form method="post" class="ajax-submit2 params-card" autocomplete="off" action="{{ route('store_settings') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Daily Swipes (Likes)') }}</label>
                                        <input type="number" class="form-control" name="daily_like_limit" value="{{ get_option('daily_like_limit', '10') }}" min="1" max="100" required>
                                        <small class="form-text text-muted">{{ _lang('Number of right-swipes a free user can make per day. Lower = more paywall triggers.') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Daily Messages') }}</label>
                                        <input type="number" class="form-control" name="daily_chat_limit" value="{{ get_option('daily_chat_limit', '3') }}" min="1" max="100" required>
                                        <small class="form-text text-muted">{{ _lang('Number of messages a free user can send per day. Lower = more paywall triggers.') }}</small>
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

            {{-- Feature Toggles Tab --}}
            <div class="tab-pane fade" id="toggles" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3 header-title card-title">{{ _lang('Feature Toggles') }}</h3>
                        <p class="text-muted mb-3">{{ _lang('Show or hide app features without releasing an update. Changes take effect on next app launch.') }}</p>
                        <form method="post" class="ajax-submit2 params-card" autocomplete="off" action="{{ route('store_settings') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Popular Tab') }}</label>
                                        <select class="form-control" name="enable_popular">
                                            <option value="1" {{ get_option('enable_popular', '1') == '1' ? 'selected' : '' }}>{{ _lang('Visible') }}</option>
                                            <option value="0" {{ get_option('enable_popular', '1') == '0' ? 'selected' : '' }}>{{ _lang('Hidden') }}</option>
                                        </select>
                                        <small class="form-text text-muted">{{ _lang('Show the Popular tab on the home screen') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Just Joined Tab') }}</label>
                                        <select class="form-control" name="enable_just_joined">
                                            <option value="1" {{ get_option('enable_just_joined', '1') == '1' ? 'selected' : '' }}>{{ _lang('Visible') }}</option>
                                            <option value="0" {{ get_option('enable_just_joined', '1') == '0' ? 'selected' : '' }}>{{ _lang('Hidden') }}</option>
                                        </select>
                                        <small class="form-text text-muted">{{ _lang('Show the Just Joined tab on the home screen') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Direct Connect') }}</label>
                                        <select class="form-control" name="enable_direct_connect">
                                            <option value="1" {{ get_option('enable_direct_connect', '1') == '1' ? 'selected' : '' }}>{{ _lang('Visible') }}</option>
                                            <option value="0" {{ get_option('enable_direct_connect', '1') == '0' ? 'selected' : '' }}>{{ _lang('Hidden') }}</option>
                                        </select>
                                        <small class="form-text text-muted">{{ _lang('Show the Add Contact / Direct Connect tab') }}</small>
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
