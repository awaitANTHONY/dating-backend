@extends('layouts.app')

@section('content')

<div class="row">
    <div class="col-md-12 mb-3">
        <h4 class="card-title">{{ _lang('Coin Transactions') }}</h4>
    </div>

    {{-- Summary Cards --}}
    <div class="col-md-4 mb-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-muted">Total Earned (Credit)</h6>
                <h3 class="text-success"><i class="fas fa-coins"></i> {{ number_format($totals['Credit'] ?? 0) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h6 class="text-muted">Total Spent (Debit)</h6>
                <h3 class="text-danger"><i class="fas fa-coins"></i> {{ number_format($totals['Debit'] ?? 0) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted">Net Balance</h6>
                <h3 class="text-primary"><i class="fas fa-coins"></i> {{ number_format(($totals['Credit'] ?? 0) - ($totals['Debit'] ?? 0)) }}</h3>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="col-md-12 mb-3">
        <form method="GET" action="{{ route('coin_transactions.index') }}" class="form-inline">
            <select name="status" class="form-control mr-2 mb-2">
                <option value="">All Types</option>
                <option value="Credit" {{ request('status') == 'Credit' ? 'selected' : '' }}>Credit (Earned)</option>
                <option value="Debit" {{ request('status') == 'Debit' ? 'selected' : '' }}>Debit (Spent)</option>
            </select>
            <input type="date" name="from" class="form-control mr-2 mb-2" value="{{ request('from') }}" placeholder="From">
            <input type="date" name="to" class="form-control mr-2 mb-2" value="{{ request('to') }}" placeholder="To">
            <input type="number" name="user_id" class="form-control mr-2 mb-2" value="{{ request('user_id') }}" placeholder="User ID">
            <button type="submit" class="btn btn-primary mb-2">Filter</button>
            <a href="{{ route('coin_transactions.index') }}" class="btn btn-secondary mb-2 ml-2">Reset</a>
        </form>
    </div>

    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $tx)
                        <tr>
                            <td>{{ $tx->id }}</td>
                            <td>
                                @if($tx->user)
                                    <a href="{{ route('users.show', $tx->user_id) }}">{{ $tx->user->name }}</a>
                                    <br><small class="text-muted">ID: {{ $tx->user_id }}</small>
                                @else
                                    <span class="text-muted">Deleted user #{{ $tx->user_id }}</span>
                                @endif
                            </td>
                            <td>
                                <strong class="{{ $tx->status == 'Credit' ? 'text-success' : 'text-danger' }}">
                                    {{ $tx->status == 'Credit' ? '+' : '-' }}{{ $tx->amount }}
                                </strong>
                            </td>
                            <td>
                                <span class="badge {{ $tx->status == 'Credit' ? 'badge-success' : 'badge-danger' }}">
                                    {{ $tx->status }}
                                </span>
                            </td>
                            <td>{{ $tx->description ?? '—' }}</td>
                            <td>
                                @if($tx->reference_type)
                                    <small>{{ $tx->reference_type }} #{{ $tx->reference_id }}</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td><small>{{ $tx->created_at->format('M j, Y H:i') }}</small></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $transactions->links() }}
            </div>
        </div>
    </div>
</div>

@endsection
