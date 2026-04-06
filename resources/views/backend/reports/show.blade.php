@extends('layouts.app')

@section('content')
@php
    $badges = ['pending' => 'warning', 'reviewed' => 'success', 'dismissed' => 'secondary'];
    $badge = $badges[$report->status] ?? 'info';
    $reportedUser = $report->reportedUser;
    $reportedInfo = optional($reportedUser)->user_information;
@endphp

<div class="row">
    <div class="col-md-6 breadcrumb-box"></div>
    <div class="col-md-6 mb-2 text-right">
        <a href="{{ url('reports') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i>
            {{ _lang('Back to Reports') }}
        </a>
    </div>

    {{-- Reported User Profile --}}
    @if($reportedUser)
    <div class="col-md-12 mb-3">
        <div class="card" style="border-left: 4px solid #dc3545;">
            <div class="card-body">
                <div class="d-flex align-items-start">
                    <img src="{{ asset($reportedUser->image) }}" class="img-thumbnail rounded-circle mr-3" style="width:100px;height:100px;object-fit:cover;">
                    <div class="flex-grow-1">
                        <h4 class="mb-1">
                            {{ $reportedUser->name }}
                            @if($reportedUser->status == 4)
                                <span class="badge badge-danger">Banned</span>
                            @else
                                <span class="badge badge-success">Active</span>
                            @endif
                            @if(optional($reportedInfo)->is_verified)
                                <span class="badge badge-info"><i class="fas fa-check-circle"></i> Verified</span>
                            @endif
                        </h4>
                        <p class="text-muted mb-1"><i class="fas fa-envelope mr-1"></i> {{ $reportedUser->email ?? 'N/A' }}</p>
                        <div class="mb-2">
                            @if(optional($reportedInfo)->gender)
                                <span class="badge badge-light mr-1"><i class="fas fa-venus-mars"></i> {{ ucfirst($reportedInfo->gender) }}</span>
                            @endif
                            @if(optional($reportedInfo)->age)
                                <span class="badge badge-light mr-1">{{ $reportedInfo->age }} yrs</span>
                            @endif
                            @if(optional($reportedInfo)->country_code)
                                <span class="badge badge-light">{{ strtoupper($reportedInfo->country_code) }}</span>
                            @endif
                        </div>
                        @if(optional($reportedInfo)->bio)
                            <p class="mb-0" style="color:#555;"><strong>Bio:</strong> {{ $reportedInfo->bio }}</p>
                        @endif
                    </div>
                </div>

                {{-- Other photos --}}
                @php
                    $otherImagesRaw = optional($reportedInfo)->images;
                    $otherImages = [];
                    if (is_array($otherImagesRaw)) {
                        $otherImages = $otherImagesRaw;
                    } elseif (is_string($otherImagesRaw) && !empty($otherImagesRaw)) {
                        $decoded = json_decode($otherImagesRaw, true);
                        if (is_array($decoded)) $otherImages = $decoded;
                    }
                @endphp
                @if(count($otherImages) > 0)
                <div class="mt-3">
                    <small class="text-muted">{{ _lang('Photos') }}:</small><br>
                    @foreach($otherImages as $img)
                        <img src="{{ asset($img) }}" class="img-thumbnail mr-1 mt-1" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
                    @endforeach
                </div>
                @endif

                {{-- Action buttons --}}
                <div class="mt-3 d-flex flex-wrap" style="gap:6px;">
                    @if($reportedUser->status == 4)
                        <a href="{{ route('users.unban', $reportedUser->id) }}" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-unlock mr-1"></i> {{ _lang('Unban User') }}
                        </a>
                    @else
                        <a href="{{ route('users.ban', $reportedUser->id) }}" class="btn btn-danger btn-sm">
                            <i class="fas fa-ban mr-1"></i> {{ _lang('Ban User') }}
                        </a>
                    @endif
                    <a href="{{ route('users.show', $reportedUser->id) }}" class="btn btn-info btn-sm">
                        <i class="fas fa-user mr-1"></i> {{ _lang('View Full Profile') }}
                    </a>
                    <form action="{{ route('users.destroy', $reportedUser->id) }}" method="post" class="ajax-delete d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove">
                            <i class="fas fa-trash-alt mr-1"></i> {{ _lang('Delete User') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Report Details --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>{{ _lang('Report Details') }}</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>{{ _lang('Reporter') }}</th>
                        <td>
                            @if($report->reporter)
                                <img src="{{ asset($report->reporter->image) }}" class="rounded-circle mr-1" style="width:28px;height:28px;object-fit:cover;">
                                {{ $report->reporter->name }}
                            @else
                                Deleted User
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>{{ _lang('Reporter Email') }}</th>
                        <td>{{ $report->reporter ? $report->reporter->email : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>{{ _lang('Reported User') }}</th>
                        <td>{{ $reportedUser ? $reportedUser->name : 'Deleted User' }}</td>
                    </tr>
                    <tr>
                        <th>{{ _lang('Reason') }}</th>
                        <td><strong>{{ $report->reason }}</strong></td>
                    </tr>
                    <tr>
                        <th>{{ _lang('Status') }}</th>
                        <td><span class="badge badge-{{ $badge }}">{{ ucfirst($report->status) }}</span></td>
                    </tr>
                    <tr>
                        <th>{{ _lang('Date') }}</th>
                        <td>{{ $report->created_at->format('M d, Y g:ia') }}</td>
                    </tr>
                </table>

                @if($report->status === 'pending')
                <div class="mt-3">
                    <a href="{{ route('reports.update-status', [$report->id, 'reviewed']) }}" class="btn btn-success btn-sm">
                        <i class="fas fa-check mr-1"></i> {{ _lang('Mark Reviewed') }}
                    </a>
                    <a href="{{ route('reports.update-status', [$report->id, 'dismissed']) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times mr-1"></i> {{ _lang('Dismiss') }}
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- All Reports Against This User --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>{{ _lang('All Reports Against') }}: {{ $reportedUser ? $reportedUser->name : 'Deleted User' }} ({{ $allReports->count() }})</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>{{ _lang('Reported By') }}</th>
                            <th>{{ _lang('Reason') }}</th>
                            <th>{{ _lang('Status') }}</th>
                            <th>{{ _lang('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($allReports as $r)
                        <tr>
                            <td>{{ $r->reporter ? $r->reporter->name : 'Deleted User' }}</td>
                            <td>{{ $r->reason }}</td>
                            <td>
                                @php $b = $badges[$r->status] ?? 'info'; @endphp
                                <span class="badge badge-{{ $b }}">{{ ucfirst($r->status) }}</span>
                            </td>
                            <td>{{ $r->created_at->format('M d, Y g:ia') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
