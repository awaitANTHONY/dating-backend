<div class="modal-body">
    @php
        $badges = ['pending' => 'warning', 'reviewed' => 'success', 'dismissed' => 'secondary'];
        $badge = $badges[$report->status] ?? 'info';
        $reportedUser = $report->reportedUser;
        $reportedInfo = optional($reportedUser)->user_information;
    @endphp

    {{-- Reported User Profile Card --}}
    @if($reportedUser)
    <div class="card mb-3" style="border-left: 4px solid #dc3545;">
        <div class="card-body">
            <div class="d-flex align-items-start">
                <img src="{{ asset($reportedUser->image) }}" class="img-thumbnail rounded-circle mr-3" style="width:80px;height:80px;object-fit:cover;">
                <div class="flex-grow-1">
                    <h5 class="mb-1">
                        {{ $reportedUser->name }}
                        @if($reportedUser->status == 4)
                            <span class="badge badge-danger">Banned</span>
                        @else
                            <span class="badge badge-success">Active</span>
                        @endif
                        @if(optional($reportedInfo)->is_verified)
                            <span class="badge badge-info"><i class="fas fa-check-circle"></i> Verified</span>
                        @endif
                    </h5>
                    <p class="text-muted mb-1"><i class="fas fa-envelope mr-1"></i> {{ $reportedUser->email ?? 'N/A' }}</p>
                    @if(optional($reportedInfo)->gender)
                        <span class="badge badge-light mr-1"><i class="fas fa-venus-mars"></i> {{ ucfirst($reportedInfo->gender) }}</span>
                    @endif
                    @if(optional($reportedInfo)->age)
                        <span class="badge badge-light mr-1">{{ $reportedInfo->age }} yrs</span>
                    @endif
                    @if(optional($reportedInfo)->country_code)
                        <span class="badge badge-light">{{ strtoupper($reportedInfo->country_code) }}</span>
                    @endif
                    @if(optional($reportedInfo)->bio)
                        <p class="mt-2 mb-0" style="font-size:0.9em;color:#555;"><strong>Bio:</strong> {{ \Illuminate\Support\Str::limit($reportedInfo->bio, 200) }}</p>
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
            <div class="mt-2">
                <small class="text-muted">Photos:</small><br>
                @foreach($otherImages as $img)
                    <img src="{{ asset($img) }}" class="img-thumbnail mr-1 mt-1" style="width:60px;height:60px;object-fit:cover;border-radius:8px;">
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Report Info --}}
    <table class="table table-bordered table-sm">
        <tr>
            <th style="width:35%;">{{ _lang('Reporter') }}</th>
            <td>
                @if($report->reporter)
                    <img src="{{ asset($report->reporter->image) }}" class="rounded-circle mr-1" style="width:24px;height:24px;object-fit:cover;">
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

    {{-- Action Buttons --}}
    <div class="mt-3 d-flex flex-wrap" style="gap:6px;">
        @if($report->status === 'pending')
            <a href="{{ route('reports.update-status', [$report->id, 'reviewed']) }}" class="btn btn-success btn-sm">
                <i class="fas fa-check mr-1"></i> {{ _lang('Mark Reviewed') }}
            </a>
            <a href="{{ route('reports.update-status', [$report->id, 'dismissed']) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-times mr-1"></i> {{ _lang('Dismiss') }}
            </a>
        @endif
        @if($reportedUser)
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
        @endif
    </div>

    <hr>

    <h6>{{ _lang('All Reports Against') }}: {{ $reportedUser ? $reportedUser->name : 'Deleted User' }} ({{ $allReports->count() }})</h6>
    <table class="table table-bordered table-sm mt-2">
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
