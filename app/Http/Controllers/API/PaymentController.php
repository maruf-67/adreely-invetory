<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin|super-admin');
    }
    public function index()
    {
        return response()->json(['success' => true, 'data' => Payment::all()]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
        $payment = Payment::create($validated);
        return response()->json(['success' => true, 'data' => $payment], 201);
    }
    public function show($id)
    {
        $payment = Payment::findOrFail($id);
        return response()->json(['success' => true, 'data' => $payment]);
    }
    public function update(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
        $payment->update($validated);
        return response()->json(['success' => true, 'data' => $payment]);
    }
    public function destroy($id)
    {
        $payment = Payment::findOrFail($id);
        $payment->delete();
        return response()->json(['success' => true, 'message' => 'Payment deleted successfully']);
    }
}
