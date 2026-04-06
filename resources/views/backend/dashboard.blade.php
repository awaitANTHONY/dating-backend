@extends('layouts.app')

@section('content')

{{-- Row 1: Key Metrics --}}
<div class="row">
	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card">
			<div class="card-body media align-items-center px-xl-3">
				<div class="u-doughnut u-doughnut--70 mr-3 mr-xl-2">
					<canvas class="js-doughnut-chart" width="70" height="70"
					data-set="[100, 0]"
					data-colors='["#2972fa","#f6f9fc"]'></canvas>
					<div class="u-doughnut__label text-info"><i class="fa fa-users"></i></div>
				</div>
				<div class="media-body">
					<span class="h2 mb-0">{{ number_format($totalUsers) }}</span>
					<h5 class="h6 text-muted text-uppercase mb-2">{{ _lang('Total Users') }}</h5>
				</div>
			</div>
		</div>
	</div>

	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card">
			<div class="card-body media align-items-center px-xl-3">
				<div class="u-doughnut u-doughnut--70 mr-3 mr-xl-2">
					<canvas class="js-doughnut-chart" width="70" height="70"
					data-set="[100, 0]"
					data-colors='["#0dd157","#f6f9fc"]'></canvas>
					<div class="u-doughnut__label text-success"><i class="fa fa-bolt"></i></div>
				</div>
				<div class="media-body">
					<span class="h2 mb-0">{{ number_format($activeToday) }}</span>
					<h5 class="h6 text-muted text-uppercase mb-2">{{ _lang('Active Today') }}</h5>
				</div>
			</div>
		</div>
	</div>

	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card">
			<div class="card-body media align-items-center px-xl-3">
				<div class="u-doughnut u-doughnut--70 mr-3 mr-xl-2">
					<canvas class="js-doughnut-chart" width="70" height="70"
					data-set="[100, 0]"
					data-colors='["#fab633","#f6f9fc"]'></canvas>
					<div class="u-doughnut__label text-warning"><i class="fa fa-crown"></i></div>
				</div>
				<div class="media-body">
					<span class="h2 mb-0">{{ number_format($totalSubscribers) }}</span>
					<h5 class="h6 text-muted text-uppercase mb-2">{{ _lang('Subscribers') }}</h5>
				</div>
			</div>
		</div>
	</div>

	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card">
			<div class="card-body media align-items-center px-xl-3">
				<div class="u-doughnut u-doughnut--70 mr-3 mr-xl-2">
					<canvas class="js-doughnut-chart" width="70" height="70"
					data-set="[100, 0]"
					data-colors='["#9b59b6","#f6f9fc"]'></canvas>
					<div class="u-doughnut__label" style="color:#9b59b6"><i class="fa fa-gem"></i></div>
				</div>
				<div class="media-body">
					<span class="h2 mb-0">{{ number_format($vipUsers) }}</span>
					<h5 class="h6 text-muted text-uppercase mb-2">{{ _lang('VIP Users') }}</h5>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- Row 2: Secondary Metrics --}}
<div class="row">
	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<span class="h3 mb-0">{{ number_format($newUsersWeek) }}</span>
						<h6 class="text-muted text-uppercase mb-0">{{ _lang('New This Week') }}</h6>
					</div>
					<div class="text-info"><i class="fa fa-user-plus fa-2x"></i></div>
				</div>
			</div>
		</div>
	</div>
	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<span class="h3 mb-0">{{ number_format($newUsersMonth) }}</span>
						<h6 class="text-muted text-uppercase mb-0">{{ _lang('New This Month') }}</h6>
					</div>
					<div class="text-primary"><i class="fa fa-calendar fa-2x"></i></div>
				</div>
			</div>
		</div>
	</div>
	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<span class="h3 mb-0">{{ number_format($pendingVerifications) }}</span>
						<h6 class="text-muted text-uppercase mb-0">{{ _lang('Pending Verifications') }}</h6>
					</div>
					<div class="text-warning"><i class="fa fa-id-card fa-2x"></i></div>
				</div>
			</div>
		</div>
	</div>
	<div class="col-sm-6 col-xl-3 mb-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<span class="h3 mb-0">{{ number_format($pendingReports) }}</span>
						<h6 class="text-muted text-uppercase mb-0">{{ _lang('Pending Reports') }}</h6>
					</div>
					<div class="text-danger"><i class="fa fa-flag fa-2x"></i></div>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- Row 3: Charts & Breakdown --}}
<div class="row">
	{{-- New User Signups Chart --}}
	<div class="col-md-8 mb-4">
		<div class="card h-100">
			<div class="card-body">
				<h5 class="card-title">{{ _lang('New Users (Last 14 Days)') }}</h5>
				<canvas id="signupChart" height="100"></canvas>
			</div>
		</div>
	</div>

	{{-- Gender Breakdown --}}
	<div class="col-md-4 mb-4">
		<div class="card h-100">
			<div class="card-body">
				<h5 class="card-title">{{ _lang('Gender Breakdown') }}</h5>
				<canvas id="genderChart" height="180"></canvas>
				<div class="mt-3">
					@foreach($genderStats as $gender => $count)
					<div class="d-flex justify-content-between mb-1">
						<span class="text-capitalize">{{ $gender }}</span>
						<strong>{{ number_format($count) }}</strong>
					</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>
</div>

{{-- Row 4: More Details --}}
<div class="row">
	{{-- Top Countries --}}
	<div class="col-md-4 mb-4">
		<div class="card h-100">
			<div class="card-body">
				<h5 class="card-title">{{ _lang('Top Countries') }}</h5>
				<table class="table table-sm table-borderless mb-0">
					<tbody>
						@forelse($topCountries as $country)
						<tr>
							<td><strong>{{ strtoupper($country->country_code) }}</strong></td>
							<td class="text-right">{{ number_format($country->count) }} users</td>
						</tr>
						@empty
						<tr><td class="text-muted">{{ _lang('No data') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	{{-- User Status Breakdown --}}
	<div class="col-md-4 mb-4">
		<div class="card h-100">
			<div class="card-body">
				<h5 class="card-title">{{ _lang('User Status') }}</h5>
				<div class="mb-3">
					<div class="d-flex justify-content-between mb-2">
						<span><i class="fa fa-circle text-success mr-1"></i> {{ _lang('Active') }}</span>
						<strong>{{ number_format($activeUsers) }}</strong>
					</div>
					<div class="d-flex justify-content-between mb-2">
						<span><i class="fa fa-circle text-warning mr-1"></i> {{ _lang('Banned') }}</span>
						<strong>{{ number_format($bannedUsers) }}</strong>
					</div>
					<div class="d-flex justify-content-between mb-2">
						<span><i class="fa fa-circle text-info mr-1"></i> {{ _lang('Verified') }}</span>
						<strong>{{ number_format($totalVerified) }}</strong>
					</div>
				</div>
				<hr>
				<div class="d-flex justify-content-between">
					<span><strong>{{ _lang('Total') }}</strong></span>
					<strong>{{ number_format($totalUsers) }}</strong>
				</div>
			</div>
		</div>
	</div>

	{{-- Quick Links --}}
	<div class="col-md-4 mb-4">
		<div class="card h-100">
			<div class="card-body">
				<h5 class="card-title">{{ _lang('Quick Actions') }}</h5>
				<div class="list-group list-group-flush">
					@if($pendingVerifications > 0)
					<a href="{{ url('verification-requests') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
						{{ _lang('Review Verifications') }}
						<span class="badge badge-warning badge-pill">{{ $pendingVerifications }}</span>
					</a>
					@endif
					@if($pendingReports > 0)
					<a href="{{ url('reports') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
						{{ _lang('Review Reports') }}
						<span class="badge badge-danger badge-pill">{{ $pendingReports }}</span>
					</a>
					@endif
					<a href="{{ url('users') }}" class="list-group-item list-group-item-action">
						<i class="fa fa-users mr-2"></i>{{ _lang('Manage Users') }}
					</a>
					<a href="{{ url('notifications') }}" class="list-group-item list-group-item-action">
						<i class="fa fa-bell mr-2"></i>{{ _lang('Send Notification') }}
					</a>
					<a href="{{ url('coin_reward_settings') }}" class="list-group-item list-group-item-action">
						<i class="fa fa-coins mr-2"></i>{{ _lang('Coin & DC Settings') }}
					</a>
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
			backgroundColor: 'rgba(41, 114, 250, 0.1)',
			borderWidth: 2,
			fill: true,
			tension: 0.3,
			pointRadius: 3,
			pointBackgroundColor: '#2972fa'
		}]
	},
	options: {
		responsive: true,
		scales: {
			yAxes: [{
				ticks: { beginAtZero: true, precision: 0 }
			}]
		},
		legend: { display: false }
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
			backgroundColor: genderColors
		}]
	},
	options: {
		responsive: true,
		legend: { position: 'bottom' }
	}
});
</script>
@endsection
