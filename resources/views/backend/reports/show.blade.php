@extends('layouts.app')

@section('content')

<div class="row">
    <div class="col-md-6 breadcrumb-box"></div>
    <div class="col-md-6 mb-2 text-right">
        <a href="{{ url('reports') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i>
            {{ _lang('Back to Reports') }}
        </a>
    </div>

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
                        <td>{{ $report->reporter ? $report->reporter->name : 'Deleted User' }}</td>
                    </tr>
                    <tr>
                        <th>{{ _lang('Reporter Email') }}</th>
                        <td>{{ $report->reporter ? $report->reporter->email : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>{{ _lang('Reported User') }}</th>
                        <td>{{ $report->reportedUser ? $report->reportedUser->name : 'Deleted User' }}</td>
                    </tr>
                    <tr>
                        <th>{{ _lang('Reason') }}</th>
                        <td>{{ $report->reason }}</td>
                    </tr>
                    <tr>
                        <th>{{ _lang('Status') }}</th>
                        <td>
                            @php
                                $badges = ['pending' => 'warning', 'reviewed' => 'success', 'dismissed' => 'secondary'];
                                $badge = $badges[$report->status] ?? 'info';
                            @endphp
                            <span class="badge badge-{{ $badge }}">{{ ucfirst($report->status) }}</span>
                        </td>
                    </tr>
                    <tr>
                        <th>{{ _lang('Date') }}</th>
                        <td>{{ $report->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                </table>

                @if($report->status === 'pending')
                <div class="mt-3">
                    <a href="{{ route('reports.update-status', [$report->id, 'reviewed']) }}" class="btn btn-success btn-sm">
                        <i class="fas fa-check mr-1"></i> {{ _lang('Mark Reviewed') }}
                    </a>
                    <a href="{{ route('reports.update-status', [$report->id, 'dismissed']) }}" class="btn btn-secondary btn-sm">
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
                <h5>{{ _lang('All Reports Against') }}: {{ $report->reportedUser ? $report->reportedUser->name : 'Deleted User' }} ({{ $allReports->count() }})</h5>
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
                            <td>{{ $r->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
