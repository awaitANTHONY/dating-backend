<?php

namespace App\Http\Controllers;

use App\Models\CoinTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoinTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = CoinTransaction::with('user')
            ->orderBy('created_at', 'desc');

        // Filter by type (Credit/Debit)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $transactions = $query->paginate(50)->withQueryString();

        // Summary totals
        $totals = CoinTransaction::select('status', DB::raw('SUM(amount) as total, COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('backend.coin_transactions.index', compact('transactions', 'totals'));
    }
}
