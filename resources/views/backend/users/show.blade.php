@extends('layouts.app')

@section('content')
@php
    $info = optional($user->user_information);
@endphp
<div class="row">
    <div class="col-md-6 breadcrumb-box"></div>
    <div class="col-md-6 mb-2 text-right">
        <a href="{{ url('users') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> {{ _lang('Back to Users') }}
        </a>
        <a href="{{ route('users.edit', $user->id) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-edit mr-1"></i> {{ _lang('Edit') }}
        </a>
    </div>

    <!-- Profile Card -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <img src="{{ asset($user->image) }}" class="img-thumbnail rounded-circle mb-3" style="width:120px;height:120px;object-fit:cover;">
                <h4 class="mb-1">{{ $user->name }}</h4>
                <p class="text-muted mb-2">{{ $user->email }}</p>

                {{-- Status badges --}}
                <div class="mb-3">
                    @if($user->status == 1)
                        <span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>
                    @elseif($user->status == 4)
                        <span class="badge badge-danger"><i class="fas fa-ban"></i> Banned</span>
                    @else
                        <span class="badge badge-secondary">Inactive</span>
                    @endif

                    @if(optional($info)->is_verified)
                        <span class="badge badge-info"><i class="fas fa-check-circle"></i> Verified</span>
                    @endif

                    @if($user->is_vip && $user->vip_expire && \Carbon\Carbon::parse($user->vip_expire)->isFuture())
                        <span class="badge" style="background:#9b59b6;color:#fff;"><i class="fas fa-gem"></i> VIP</span>
                    @endif

                    @if($user->subscription_id && $user->subscription_id > 0)
                        <span class="badge badge-warning"><i class="fas fa-crown"></i> {{ optional($user->subscription)->name ?? 'Subscriber' }}</span>
                    @endif
                </div>

                {{-- Quick stats --}}
                <div class="row text-center mb-2">
                    <div class="col-4">
                        <strong style="font-size:1.3em;">{{ number_format($user->wallet_balance ?? 0) }}</strong>
                        <br><small class="text-muted">Wallet</small>
                    </div>
                    <div class="col-4">
                        <strong style="font-size:1.3em;">{{ number_format($user->coin_balance ?? 0) }}</strong>
                        <br><small class="text-muted">Coins</small>
                    </div>
                    <div class="col-4">
                        <strong style="font-size:1.3em;">{{ $user->created_at ? $user->created_at->format('M Y') : '-' }}</strong>
                        <br><small class="text-muted">Joined</small>
                    </div>
                </div>

                {{-- Ban / Unban --}}
                <div class="mt-3">
                    @if($user->status == 4)
                        <a href="{{ route('users.unban', $user->id) }}" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-unlock mr-1"></i> {{ _lang('Unban') }}
                        </a>
                    @else
                        <a href="{{ route('users.ban', $user->id) }}" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-ban mr-1"></i> {{ _lang('Ban') }}
                        </a>
                    @endif
                    <form action="{{ route('users.destroy', $user->id) }}" method="post" class="ajax-delete d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove">
                            <i class="fas fa-trash-alt mr-1"></i> {{ _lang('Delete') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Other Photos --}}
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-2">{{ _lang('Photos') }}</h6>
                @php
                    $otherImagesRaw = optional($info)->images;
                    $otherImages = [];
                    if (is_array($otherImagesRaw)) {
                        $otherImages = $otherImagesRaw;
                    } elseif (is_string($otherImagesRaw) && !empty($otherImagesRaw)) {
                        $decoded = json_decode($otherImagesRaw, true);
                        if (is_array($decoded)) $otherImages = $decoded;
                    }
                @endphp
                @if(count($otherImages) > 0)
                    <div class="d-flex flex-wrap" style="gap:6px;">
                    @foreach($otherImages as $img)
                        <img src="{{ asset($img) }}" class="img-thumbnail" style="width:70px;height:70px;object-fit:cover;border-radius:8px;">
                    @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No additional photos</p>
                @endif
            </div>
        </div>

        {{-- Wallet & Coins --}}
        <div class="card mb-3">
            <div class="card-body text-center">
                <h6 class="mb-3">{{ _lang('Wallet & Coins') }}</h6>
                <a href="{{ route('users.wallet_manage', $user->id) }}" class="btn btn-sm mb-2" style="background:#6c00ff;color:#fff;font-weight:600;border-radius:20px;padding:8px 24px;">
                    <i class="fas fa-wallet mr-1"></i> Wallet Operation
                </a>
                <a href="{{ route('users.coin_manage', $user->id) }}" class="btn btn-sm" style="background:#eab308;color:#fff;font-weight:600;border-radius:20px;padding:8px 24px;">
                    <i class="fas fa-coins mr-1"></i> Coin Operation
                </a>
            </div>
        </div>
    </div>

    <!-- Details -->
    <div class="col-md-8">
        {{-- Bio --}}
        @if(optional($info)->bio)
        <div class="card mb-3">
            <div class="card-body">
                <h6><i class="fas fa-quote-left text-muted mr-1"></i> {{ _lang('Bio') }}</h6>
                <p class="mb-0">{!! nl2br(e($info->bio)) !!}</p>
            </div>
        </div>
        @endif

        {{-- Personal Info --}}
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-3">{{ _lang('Personal Information') }}</h6>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width:40%;">Gender</td>
                                <td><strong>{{ ucfirst(optional($info)->gender ?? '-') }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Age</td>
                                <td><strong>{{ optional($info)->age ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Birth Date</td>
                                <td><strong>{{ optional($info)->date_of_birth ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Height</td>
                                <td><strong>{{ optional($info)->height ? $info->height . ' cm' : '-' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Country</td>
                                <td><strong>{{ optional($info)->country_code ? strtoupper($info->country_code) : '-' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Phone</td>
                                <td><strong>{{ optional($info)->phone ?? '-' }}</strong></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width:40%;">Religion</td>
                                <td><strong>{{ optional(optional($info)->religion)->title ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Relationship</td>
                                <td><strong>{{ optional(optional($info)->relationshipStatus)->title ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Ethnicity</td>
                                <td><strong>{{ optional(optional($info)->ethnicity)->title ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Education</td>
                                <td><strong>{{ optional(optional($info)->education)->title ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Career</td>
                                <td><strong>{{ optional(optional($info)->careerField)->title ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Alcohol</td>
                                <td><strong>{{ optional($info)->alkohol ? ucwords(str_replace('_', ' ', $info->alkohol)) : '-' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Smoking</td>
                                <td><strong>{{ optional($info)->smoke ? ucwords(str_replace('_', ' ', $info->smoke)) : '-' }}</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Matching Preferences --}}
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-3">{{ _lang('Matching Preferences') }}</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><span class="text-muted">Search Preference:</span> <strong>{{ is_array(optional($info)->search_preference) ? implode(', ', $info->search_preference) : (optional($info)->search_preference ?? '-') }}</strong></p>
                        <p><span class="text-muted">Preferred Age:</span> <strong>{{ optional($info)->preffered_age ?? '-' }}</strong></p>
                        <p><span class="text-muted">Search Radius:</span> <strong>{{ optional($info)->search_radius ?? '-' }} KM</strong></p>
                    </div>
                    <div class="col-md-6">
                        <p><span class="text-muted">Zodiac Matters:</span> <strong>{{ optional($info)->is_zodiac_sign_matter ? 'Yes' : 'No' }}</strong></p>
                        <p><span class="text-muted">Food Pref Matters:</span> <strong>{{ optional($info)->is_food_preference_matter ? 'Yes' : 'No' }}</strong></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Subscription --}}
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-3">{{ _lang('Subscription & Plan') }}</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><span class="text-muted">Plan:</span> <strong>{{ optional($user->subscription)->name ?? 'Free' }}</strong></p>
                        <p><span class="text-muted">Expires:</span> <strong>{{ $user->expired_at ?? '-' }}</strong></p>
                    </div>
                    <div class="col-md-6">
                        <p><span class="text-muted">VIP:</span> <strong>{{ $user->is_vip ? 'Yes' : 'No' }}</strong></p>
                        <p><span class="text-muted">VIP Expires:</span> <strong>{{ $user->vip_expire ?? '-' }}</strong></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Relation Goals --}}
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-2">{{ _lang('Relation Goals') }}</h6>
                @php $goals = optional($info)->relation_goals_details; @endphp
                @if(is_iterable($goals) && count($goals))
                    @foreach($goals as $goal)
                        <span class="badge badge-light mr-1 mb-1" style="font-size:0.9em;padding:6px 12px;">{{ $goal->title }}</span>
                    @endforeach
                @else
                    <span class="text-muted">-</span>
                @endif
            </div>
        </div>

        {{-- Interests --}}
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-2">{{ _lang('Interests') }}</h6>
                @php $interests = optional($info)->interests_details; @endphp
                @if(is_iterable($interests) && count($interests))
                    @foreach($interests as $interest)
                        <span class="d-inline-block text-center mx-2 mb-2">
                            <img src="{{ asset($interest->image) }}" class="img-thumbnail rounded-circle" style="width:50px;height:50px;object-fit:cover;"><br>
                            <small>{{ $interest->title }}</small>
                        </span>
                    @endforeach
                @else
                    <span class="text-muted">-</span>
                @endif
            </div>
        </div>

        {{-- Languages --}}
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-2">{{ _lang('Languages Known') }}</h6>
                @php $languages = optional($info)->languages_details; @endphp
                @if(is_iterable($languages) && count($languages))
                    @foreach($languages as $language)
                        <span class="d-inline-block text-center mx-2 mb-2">
                            <img src="{{ asset($language->image) }}" class="img-thumbnail rounded-circle" style="width:50px;height:50px;object-fit:cover;"><br>
                            <small>{{ $language->title }}</small>
                        </span>
                    @endforeach
                @else
                    <span class="text-muted">-</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
