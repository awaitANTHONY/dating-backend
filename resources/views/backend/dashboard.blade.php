@extends('layouts.app')

@section('content')

{{-- Welcome Header --}}
<div class="row mb-3">
	<div class="col-12">
		<h4 class="mb-0">Welcome back, {{ Auth::user()->name ?? 'Admin' }}</h4>
		<small class="text-muted">{{ \Carbon\Carbon::now()->format('l, M d, Y \a\t g:ia') }}</small>
	</div>
</div>

{{-- Row 1: Key Metrics (4 cards like ezhire) --}}
<div class="row">
	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-start">
					<div>
						<p class="text-muted text-uppercase mb-1" style="font-size:0.75em;letter-spacing:0.5px;">Total Users</p>
						<h3 class="mb-1 font-weight-bold">{{ number_format($totalUsers) }}</h3>
						<small class="{{ $userGrowth >= 0 ? 'text-success' : 'text-danger' }}">
							<i class="fas fa-arrow-{{ $userGrowth >= 0 ? 'up' : 'down' }}"></i>
							{{ abs($userGrowth) }}% vs last month
						</small>
					</div>
					<div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(41,114,250,0.1);">
						<i class="fas fa-users" style="color:#2972fa;font-size:1.2em;"></i>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-start">
					<div>
						<p class="text-muted text-uppercase mb-1" style="font-size:0.75em;letter-spacing:0.5px;">Active Today</p>
						<h3 class="mb-1 font-weight-bold">{{ number_format($activeToday) }}</h3>
						<small class="text-muted">Online in last 24h</small>
					</div>
					<div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(13,209,87,0.1);">
						<i class="fas fa-bolt" style="color:#0dd157;font-size:1.2em;"></i>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-start">
					<div>
						<p class="text-muted text-uppercase mb-1" style="font-size:0.75em;letter-spacing:0.5px;">Subscribers</p>
						<h3 class="mb-1 font-weight-bold">{{ number_format($totalSubscribers) }}</h3>
						<small class="text-muted">{{ number_format($vipUsers) }} VIP</small>
					</div>
					<div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(250,182,51,0.1);">
						<i class="fas fa-crown" style="color:#fab633;font-size:1.2em;"></i>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-start">
					<div>
						<p class="text-muted text-uppercase mb-1" style="font-size:0.75em;letter-spacing:0.5px;">New This Week</p>
						<h3 class="mb-1 font-weight-bold">{{ number_format($newUsersWeek) }}</h3>
						<small class="text-muted">{{ number_format($newUsersMonth) }} this month</small>
					</div>
					<div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(155,89,182,0.1);">
						<i class="fas fa-user-plus" style="color:#9b59b6;font-size:1.2em;"></i>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- Row 2: Alerts bar --}}
@if($pendingVerifications > 0 || $pendingReports > 0)
<div class="row mb-3">
	<div class="col-12">
		<div class="card border-0 shadow-sm" style="background:linear-gradient(135deg,#fff5f5,#fff);">
			<div class="card-body py-2 d-flex align-items-center flex-wrap" style="gap:16px;">
				<span class="text-muted mr-2"><i class="fas fa-exclamation-triangle text-warning"></i> <strong>Needs Attention:</strong></span>
				@if($pendingVerifications > 0)
				<a href="{{ url('verification-requests') }}" class="btn btn-warning btn-sm">
					<i class="fas fa-id-card mr-1"></i> {{ $pendingVerifications }} Pending Verifications
				</a>
				@endif
				@if($pendingReports > 0)
				<a href="{{ url('reports?status=pending') }}" class="btn btn-danger btn-sm">
					<i class="fas fa-flag mr-1"></i> {{ $pendingReports }} Pending Reports
				</a>
				@endif
			</div>
		</div>
	</div>
</div>
@endif

{{-- Row 3: Charts & Subscription Breakdown --}}
<div class="row">
	{{-- Signup Trend Chart --}}
	<div class="col-lg-8 mb-4">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h6 class="mb-0 font-weight-bold">User Growth (Last 14 Days)</h6>
					<span class="badge badge-light">{{ number_format(array_sum($chartData)) }} signups</span>
				</div>
				<canvas id="signupChart" height="90"></canvas>
			</div>
		</div>
	</div>

	{{-- Subscription Breakdown --}}
	<div class="col-lg-4 mb-4">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-body">
				<h6 class="mb-3 font-weight-bold">Subscription Overview</h6>
				<canvas id="subChart" height="160"></canvas>

				<div class="mt-3">
					{{-- VIP --}}
					<div class="d-flex justify-content-between align-items-center mb-2">
						<span><span class="d-inline-block rounded-circle mr-2" style="width:10px;height:10px;background:#9b59b6;"></span>VIP</span>
						<strong>{{ number_format($vipUsers) }}</strong>
					</div>
					{{-- Per plan --}}
					@foreach($subscriptionBreakdown as $sub)
					<div class="d-flex justify-content-between align-items-center mb-2">
						<span>
							<span class="d-inline-block rounded-circle mr-2" style="width:10px;height:10px;background:{{ str_contains(strtolower($sub->name), 'gold') ? '#f59e0b' : '#2972fa' }};"></span>
							{{ $sub->name }}
						</span>
						<strong>{{ number_format($sub->count) }}</strong>
					</div>
					@endforeach
					{{-- Expired --}}
					@if($expiredSubscribers > 0)
					<div class="d-flex justify-content-between align-items-center mb-2">
						<span><span class="d-inline-block rounded-circle mr-2" style="width:10px;height:10px;background:#adb5bd;"></span>Expired</span>
						<strong>{{ number_format($expiredSubscribers) }}</strong>
					</div>
					@endif
					{{-- Free --}}
					<div class="d-flex justify-content-between align-items-center">
						<span><span class="d-inline-block rounded-circle mr-2" style="width:10px;height:10px;background:#e9ecef;"></span>Free</span>
						<strong>{{ number_format($freeUsers) }}</strong>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- Row 4: Recent Users & Gender/Countries --}}
<div class="row">
	{{-- Recent Users Table --}}
	<div class="col-lg-8 mb-4">
		<div class="card border-0 shadow-sm">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h6 class="mb-0 font-weight-bold">Recent Users</h6>
					<a href="{{ url('users') }}" class="btn btn-outline-primary btn-sm">View All</a>
				</div>
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead style="background:#f8f9fa;">
							<tr>
								<th style="border-top:0;">User</th>
								<th style="border-top:0;">Country</th>
								<th style="border-top:0;">Plan</th>
								<th style="border-top:0;">Status</th>
								<th style="border-top:0;">Joined</th>
							</tr>
						</thead>
						<tbody>
							@foreach($recentUsers as $u)
							@php
								$uInfo = optional($u->user_information);
								$cc = $uInfo->country_code ? strtoupper(trim($uInfo->country_code)) : null;
								$flag = '';
								if ($cc && strlen($cc) == 2) {
									$flag = collect(str_split($cc))->map(function ($c) {
										return mb_chr(ord($c) - ord('A') + 0x1F1E6);
									})->implode('');
								}
							@endphp
							<tr>
								<td>
									<div class="d-flex align-items-center">
										<img src="{{ asset($u->image) }}" class="rounded-circle mr-2" style="width:32px;height:32px;object-fit:cover;">
										<div>
											<strong style="font-size:0.9em;">{{ $u->name }}</strong>
											@if($uInfo->is_verified)
												<i class="fas fa-check-circle text-info ml-1" style="font-size:0.7em;"></i>
											@endif
											<br><small class="text-muted">{{ $u->email }}</small>
										</div>
									</div>
								</td>
								<td class="align-middle">{{ $flag }} {{ $cc ?? '-' }}</td>
								<td class="align-middle">
									@if($u->is_vip && $u->vip_expire && \Carbon\Carbon::parse($u->vip_expire)->isFuture())
										<span class="badge" style="background:#9b59b6;color:#fff;font-size:0.75em;">VIP</span>
									@elseif($u->subscription_id && $u->subscription_id > 0)
										@php
											$sName = optional($u->subscription)->name ?? 'Sub';
											$sExpired = $u->expired_at && \Carbon\Carbon::parse($u->expired_at)->isPast();
										@endphp
										@if($sExpired)
											<span class="badge badge-secondary" style="font-size:0.75em;">{{ $sName }} (Exp)</span>
										@elseif(str_contains(strtolower($sName), 'gold'))
											<span class="badge" style="background:#f59e0b;color:#fff;font-size:0.75em;">Gold</span>
										@else
											<span class="badge badge-primary" style="font-size:0.75em;">{{ $sName }}</span>
										@endif
									@else
										<span class="badge badge-light" style="font-size:0.75em;">Free</span>
									@endif
								</td>
								<td class="align-middle">
									@if($u->status == 1)
										<span class="badge badge-success" style="font-size:0.75em;">Active</span>
									@elseif($u->status == 4)
										<span class="badge badge-danger" style="font-size:0.75em;">Banned</span>
									@else
										<span class="badge badge-secondary" style="font-size:0.75em;">Inactive</span>
									@endif
								</td>
								<td class="align-middle"><small>{{ $u->created_at->format('M d, g:ia') }}</small></td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	{{-- Right Column: Gender + Countries --}}
	<div class="col-lg-4 mb-4">
		{{-- Gender Breakdown --}}
		<div class="card border-0 shadow-sm mb-4">
			<div class="card-body">
				<h6 class="mb-3 font-weight-bold">Gender Breakdown</h6>
				<canvas id="genderChart" height="150"></canvas>
				<div class="mt-3">
					@foreach($genderStats as $gender => $count)
					<div class="d-flex justify-content-between mb-1">
						<span>
							@if($gender === 'male')
								<i class="fas fa-mars text-primary mr-1"></i>
							@elseif($gender === 'female')
								<i class="fas fa-venus mr-1" style="color:#e74c8b;"></i>
							@else
								<i class="fas fa-genderless text-warning mr-1"></i>
							@endif
							{{ ucfirst($gender) }}
						</span>
						<strong>{{ number_format($count) }}</strong>
					</div>
					@endforeach
				</div>
			</div>
		</div>

		{{-- Top Countries --}}
		<div class="card border-0 shadow-sm">
			<div class="card-body">
				<h6 class="mb-3 font-weight-bold">Top Countries</h6>
				@forelse($topCountries as $i => $country)
				@php
					$cc = strtoupper(trim($country->country_code));
					$flag = '';
					if (strlen($cc) == 2) {
						$flag = collect(str_split($cc))->map(function ($c) {
							return mb_chr(ord($c) - ord('A') + 0x1F1E6);
						})->implode('');
					}
					$pct = $totalUsers > 0 ? round(($country->count / $totalUsers) * 100, 1) : 0;
				@endphp
				<div class="mb-2">
					<div class="d-flex justify-content-between mb-1">
						<span>
							@if($i < 3)<strong>@endif
							{{ $flag }} {{ $cc }}
							@if($i < 3)</strong>@endif
						</span>
						<span>{{ number_format($country->count) }} <small class="text-muted">({{ $pct }}%)</small></span>
					</div>
					<div class="progress" style="height:4px;">
						<div class="progress-bar" role="progressbar" style="width:{{ $pct }}%;background:{{ $i == 0 ? '#2972fa' : ($i == 1 ? '#0dd157' : '#fab633') }};" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
					</div>
				</div>
				@empty
				<p class="text-muted mb-0">No data</p>
				@endforelse
			</div>
		</div>
	</div>
</div>

{{-- Row 5: User Status + Quick Actions --}}
<div class="row">
	<div class="col-md-6 mb-4">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-body">
				<h6 class="mb-3 font-weight-bold">User Status</h6>
				<div class="d-flex justify-content-between mb-3">
					<div class="text-center flex-fill">
						<h4 class="mb-0 text-success">{{ number_format($activeUsers) }}</h4>
						<small class="text-muted">Active</small>
					</div>
					<div class="text-center flex-fill" style="border-left:1px solid #eee;border-right:1px solid #eee;">
						<h4 class="mb-0 text-danger">{{ number_format($bannedUsers) }}</h4>
						<small class="text-muted">Banned</small>
					</div>
					<div class="text-center flex-fill">
						<h4 class="mb-0 text-info">{{ number_format($totalVerified) }}</h4>
						<small class="text-muted">Verified</small>
					</div>
				</div>
				<hr>
				<div class="d-flex justify-content-between">
					<span class="text-muted">Total Registered</span>
					<strong>{{ number_format($totalUsers) }}</strong>
				</div>
			</div>
		</div>
	</div>
	<div class="col-md-6 mb-4">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-body">
				<h6 class="mb-3 font-weight-bold">Quick Actions</h6>
				<div class="d-flex flex-wrap" style="gap:8px;">
					<a href="{{ url('users') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-users mr-1"></i> Manage Users</a>
					<a href="{{ url('notifications') }}" class="btn btn-outline-info btn-sm"><i class="fas fa-bell mr-1"></i> Send Notification</a>
					<a href="{{ url('reports?status=pending') }}" class="btn btn-outline-danger btn-sm"><i class="fas fa-flag mr-1"></i> Reports</a>
					<a href="{{ url('verification-requests') }}" class="btn btn-outline-warning btn-sm"><i class="fas fa-id-card mr-1"></i> Verifications</a>
					<a href="{{ url('coin_reward_settings') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-coins mr-1"></i> Coin Settings</a>
					<a href="{{ url('mood_suggestions') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-smile mr-1"></i> Mood Suggestions</a>
					<a href="{{ url('contact_platforms') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-address-book mr-1"></i> Contact Platforms</a>
				</div>
			</div>
		</div>
	</div>
</div>

@endsection

@section('js-script')
<script src="{{ asset('public/backend') }}/vendor/chart.js/dist/Chart.min.js"></script>
<script>
// Signup trend chart
var ctx = document.getElementById('signupChart').getContext('2d');
new Chart(ctx, {
	type: 'line',
	data: {
		labels: {!! json_encode($chartLabels) !!},
		datasets: [{
			label: 'New Users',
			data: {!! json_encode($chartData) !!},
			borderColor: '#2972fa',
			backgroundColor: 'rgba(41, 114, 250, 0.08)',
			borderWidth: 2,
			fill: true,
			tension: 0.4,
			pointRadius: 3,
			pointBackgroundColor: '#2972fa',
			pointHoverRadius: 5
		}]
	},
	options: {
		responsive: true,
		scales: {
			yAxes: [{
				ticks: { beginAtZero: true, precision: 0 },
				gridLines: { color: 'rgba(0,0,0,0.04)' }
			}],
			xAxes: [{
				gridLines: { display: false }
			}]
		},
		legend: { display: false },
		tooltips: {
			backgroundColor: '#1a1a2e',
			titleFontSize: 12,
			bodyFontSize: 13,
			cornerRadius: 6
		}
	}
});

// Subscription doughnut chart
var subCtx = document.getElementById('subChart').getContext('2d');
var subLabels = ['VIP'];
var subData = [{{ $vipUsers }}];
var subColors = ['#9b59b6'];
@foreach($subscriptionBreakdown as $sub)
subLabels.push({!! json_encode($sub->name) !!});
subData.push({{ $sub->count }});
subColors.push({!! json_encode(str_contains(strtolower($sub->name), 'gold') ? '#f59e0b' : '#2972fa') !!});
@endforeach
subLabels.push('Free');
subData.push({{ $freeUsers }});
subColors.push('#e9ecef');

new Chart(subCtx, {
	type: 'doughnut',
	data: {
		labels: subLabels,
		datasets: [{
			data: subData,
			backgroundColor: subColors,
			borderWidth: 2,
			borderColor: '#fff'
		}]
	},
	options: {
		responsive: true,
		cutoutPercentage: 65,
		legend: { display: false },
		tooltips: {
			backgroundColor: '#1a1a2e',
			cornerRadius: 6
		}
	}
});

// Gender chart
var genderCtx = document.getElementById('genderChart').getContext('2d');
var genderData = @json($genderStats);
var genderLabels = Object.keys(genderData).map(function(g) { return g.charAt(0).toUpperCase() + g.slice(1); });
var genderValues = Object.values(genderData);
var genderColors = [];
Object.keys(genderData).forEach(function(g) {
	if (g === 'male') genderColors.push('#2972fa');
	else if (g === 'female') genderColors.push('#e74c8b');
	else genderColors.push('#fab633');
});

new Chart(genderCtx, {
	type: 'doughnut',
	data: {
		labels: genderLabels,
		datasets: [{
			data: genderValues,
			backgroundColor: genderColors,
			borderWidth: 2,
			borderColor: '#fff'
		}]
	},
	options: {
		responsive: true,
		cutoutPercentage: 65,
		legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
		tooltips: {
			backgroundColor: '#1a1a2e',
			cornerRadius: 6
		}
	}
});
</script>
@endsection
