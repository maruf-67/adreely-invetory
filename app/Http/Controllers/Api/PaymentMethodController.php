<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of payment methods for the business.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $paymentMethods = PaymentMethod::where('business_id', $user->business_id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $paymentMethods
        ]);
    }

    /**
     * Store a newly created payment method.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gateway_name' => 'required|string|max:255',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        $paymentMethod = PaymentMethod::create([
            'business_id' => $user->business_id,
            'gateway_name' => $request->gateway_name,
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'branch' => $request->branch,
            'currency' => $request->currency,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment method created successfully',
            'data' => $paymentMethod
        ], 201);
    }

    /**
     * Display the specified payment method.
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        $paymentMethod = PaymentMethod::where('business_id', $user->business_id)->find($id);

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $paymentMethod
        ]);
    }

    /**
     * Update the specified payment method.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $paymentMethod = PaymentMethod::where('business_id', $user->business_id)->find($id);

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'gateway_name' => 'required|string|max:255',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $paymentMethod->update($request->only([
            'gateway_name',
            'account_name',
            'account_number',
            'branch',
            'currency',
            'is_active'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Payment method updated successfully',
            'data' => $paymentMethod
        ]);
    }

    /**
     * Remove the specified payment method.
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $paymentMethod = PaymentMethod::where('business_id', $user->business_id)->find($id);

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found'
            ], 404);
        }

        // Check if payment method is being used
        if ($paymentMethod->payments()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete payment method that has associated payments'
            ], 400);
        }

        $paymentMethod->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully'
        ]);
    }

    /**
     * Toggle payment method status.
     */
    public function toggleStatus($id): JsonResponse
    {
        $user = Auth::user();
        $paymentMethod = PaymentMethod::where('business_id', $user->business_id)->find($id);

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found'
            ], 404);
        }

        $paymentMethod->update(['is_active' => !$paymentMethod->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Payment method status updated successfully',
            'data' => $paymentMethod
        ]);
    }
}
