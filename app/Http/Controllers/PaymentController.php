<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()){
            $payments = Payment::with(['user', 'subscription'])
                ->orderBy('id', 'DESC')
                ->paginate(15);
            
            return response()->json([
                'data' => $payments->items(),
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ]);
        }

        return view('backend.payments.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (!$request->ajax()) {
            return view('backend.payments.modal.show', compact('payment'));
        } else {
            return view('backend.payments.modal.show', compact('payment'));
        }
    }
}

