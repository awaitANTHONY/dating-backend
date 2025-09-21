@extends('layouts.app')

@section('content')
<h2 class="card-title d-none">{{ _lang('Details') }}</h2>
<div class="row">
    
    <!-- Profile and Other Images -->
    <div class="col-md-3">
        <div class="card mb-3">
            <div class="card-body text-center">
                <h5>{{ _lang('My Profile') }}</h5>
                <img src="{{ $user->image }}" class="img-lg img-thumbnail rounded-circle mb-2">
                <div><b>{{ $user->name }}</b></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body text-center">
                <h5>{{ _lang('Other Picture') }}</h5>
                @php
                    $otherImagesRaw = optional($user->user_information)->images;
                    $otherImages = [];
                    if (is_array($otherImagesRaw)) {
                        $otherImages = $otherImagesRaw;
                    } elseif (is_string($otherImagesRaw) && !empty($otherImagesRaw)) {
                        $decoded = json_decode($otherImagesRaw, true);
                        if (is_array($decoded)) $otherImages = $decoded;
                    }
                @endphp
                @foreach($otherImages as $img)
                    <img src="{{ asset($img) }}" class="img-thumbnail rounded-circle mx-1" style="width:60px;height:60px;object-fit:cover;">
                @endforeach
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body text-center">
                <h5>{{ _lang('Wallet and Coin Operations') }}</h5>
                <a href="{{ route('users.wallet_manage', $user->id) }}" class="btn mb-3" style="background:#6c00ff;color:#fff;font-weight:600;border-radius:24px;padding:10px 32px;font-size:1.1em;">Wallet Operation</a>

                <a href="{{ route('users.coin_manage', $user->id) }}" class="btn" style="background:#eab308;color:#fff;font-weight:600;border-radius:24px;padding:10px 32px;font-size:1.1em;">Coin Operation</a>
            </div>
        </div>
    </div>
    <!-- Other Information -->
    <div class="col-md-9">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                    <h5>{{ _lang('Other Information') }}</h5>
                    </div>
                    <div class="col-md-6">
                        <p><b>Email:</b> {{ strtoupper($user->email) }}</p>
                        <p><b>Phone:</b> {{ optional($user->user_information)->country_code }}{{ optional($user->user_information)->phone }}</p>
                        <p><b>Profile Bio:</b><br> {!! nl2br(e(optional($user->user_information)->bio)) !!}</p>
                        <p><b>Search Preference:</b> {{ is_array(optional($user->user_information)->search_preference) ? implode(', ', optional($user->user_information)->search_preference) : (optional($user->user_information)->search_preference ?? '-') }}</p>
                        <p><b>Gender:</b> {{ strtoupper(optional($user->user_information)->gender) }}</p>
                        <p><b>Radius Search:</b> {{ optional($user->user_information)->search_radius ?? '-' }}KM</p>
                    </div>
                    <div class="col-md-6">
                        <p><b>Birth Date:</b> {{ optional($user->user_information)->date_of_birth }}</p>
                        <p><b>Relation Goal:</b><br>
                            @php
                                $goals = optional($user->user_information)->relation_goals_details;
                            @endphp
                            @if(is_iterable($goals) && count($goals))
                                @foreach($goals as $goal)
                                    <span class="d-inline-block text-center mx-2">
                                        
                                        {{ $goal->title }}
                                    </span>
                                @endforeach
                            @else
                                -
                            @endif
                        </p>
                        <p><b>Religion:</b> {{ optional($user->user_information)->religion->title }}</p>
                        <p><b>Wallet Balance:</b> {{ $user->wallet_balance }}$</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <h5>{{ _lang('Plan Information') }}</h5>
                <p><b>Plan:</b> {{ optional($user->subscription)->name }}</p>
                <p><b>Expire At:</b> {{ $user->expire_at ?? '-' }}</p>
            </div>
        </div>
        <!-- Interests -->
        <div class="card mb-3">
            <div class="card-body">
                <h5>{{ _lang('Interest') }}</h5>
                @php
                    $interests = optional($user->user_information)->interests_details;
                @endphp
                @if(is_iterable($interests) && count($interests))
                    @foreach($interests as $interest)
                        <span class="d-inline-block text-center mx-2">
                            <span style="font-size:2em;"><img src="{{ asset($interest->image) }}" class="img-lg img-thumbnail rounded-circle mb-2"></span><br>
                            {{ $interest->title }}
                        </span>
                    @endforeach
                @else
                    -
                @endif
            </div>
        </div>
        <!-- Languages -->
        <div class="card mb-3">
            <div class="card-body">
                <h5>{{ _lang('Languages Known') }}</h5>
                @php
                    $languages = optional($user->user_information)->languages_details;
                @endphp
                @if(is_iterable($languages) && count($languages))
                    @foreach($languages as $language)

                    
                        <span class="d-inline-block text-center mx-2">
                            <span style="font-size:2em;"><img src="{{ asset($language->image) }}" class="img-lg img-thumbnail rounded-circle mb-2"></span><br>
                            {{ $language->title }}
                        </span>
                    @endforeach
                @else
                    -
                @endif
               
            </div>
        </div>
    </div>
</div>
@endsection

