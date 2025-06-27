<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChequePayment;
use Illuminate\Http\Request;

class ChequePaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin|super-admin');
    }
    public function index()
    {
        return response()->json(['success' => true, 'data' => ChequePayment::all()]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'related_type' => 'required|in:purchase,sales',
            'related_id' => 'required|integer',
            'status' => 'required|in:draft,clear,hold,rejected',
            'amount' => 'required|numeric|min:0',
            'bank_name' => 'nullable|string',
            'cheque_number' => 'nullable|string',
            'updated_by' => 'required|exists:users,id',
        ]);
        $chequePayment = ChequePayment::create($validated);
        return response()->json(['success' => true, 'data' => $chequePayment], 201);
    }
    public function show($id)
    {
        $chequePayment = ChequePayment::findOrFail($id);
        return response()->json(['success' => true, 'data' => $chequePayment]);
    }
    public function update(Request $request, $id)
    {
        $chequePayment = ChequePayment::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:draft,clear,hold,rejected',
            'amount' => 'required|numeric|min:0',
            'bank_name' => 'nullable|string',
            'cheque_number' => 'nullable|string',
            'updated_by' => 'required|exists:users,id',
        ]);
        $chequePayment->update($validated);
        return response()->json(['success' => true, 'data' => $chequePayment]);
    }
    public function destroy($id)
    {
        $chequePayment = ChequePayment::findOrFail($id);
        $chequePayment->delete();
        return response()->json(['success' => true, 'message' => 'Cheque payment deleted successfully']);
    }
}
