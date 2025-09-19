@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12 mx-auto">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">Coin Management</h3>
                    <span class="badge bg-info text-white p-2" style="font-size:1.1em;border-radius:24px;">
                        Coin Balance: {{ $coinBalance }} <i class="fa fa-coins"></i>
                    </span>
                </div>
                <form method="POST" action="{{ route('users.update_coin', $user->id) }}">
                    @csrf
                    <div class="mb-3">
                        <label for="type" class="form-label">Type</label>
                        <select name="type" id="type" class="form-control" required>
                            <option value="Credit">Add Balance</option>
                            <option value="Debit">Subtract Balance</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Enter Coin</label>
                        <input type="number" name="amount" id="amount" class="form-control" placeholder="Enter Coin" min="1" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-coins"></i> Update Coin Balance
                    </button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h4>Coin Log</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($coinLogs as $i => $log)
                                <tr>
                                    <td>{{ $i+1 }}</td>
                                    <td>{{ $log->amount }}</td>
                                    <td class="{{ $log->status == 'Debit' ? 'text-danger' : 'text-success' }}">{{ $log->status }}</td>
                                    <td>{{ $log->created_at->format('jS F, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
