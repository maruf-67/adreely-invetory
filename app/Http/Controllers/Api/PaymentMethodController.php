<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of payment methods.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = PaymentMethod::where('business_id', $user->business_id);

        // Filter by active status
        if ($request->has('active_only') && $request->active_only) {
            $query->where('is_active', true);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $paymentMethods = $query->orderBy('name')->get();

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
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,bank_transfer,cheque,credit_card,digital_wallet,mobile_banking,other',
            'details' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();

            $paymentMethod = PaymentMethod::create([
                'business_id' => $user->business_id,
                'name' => $request->name,
                'type' => $request->type,
                'details' => $request->details,
                'is_active' => $request->get('is_active', true),
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment method created successfully',
                'data' => $paymentMethod
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment method',
                'error' => $e->getMessage()
            ], 500);
        }
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
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:cash,bank_transfer,cheque,credit_card,digital_wallet,mobile_banking,other',
            'details' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $paymentMethod->update([
                'name' => $request->get('name', $paymentMethod->name),
                'type' => $request->get('type', $paymentMethod->type),
                'details' => $request->has('details') ? $request->details : $paymentMethod->details,
                'is_active' => $request->get('is_active', $paymentMethod->is_active),
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment method updated successfully',
                'data' => $paymentMethod
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment method',
                'error' => $e->getMessage()
            ], 500);
        }
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
                'message' => 'Cannot delete payment method that has been used in transactions'
            ], 400);
        }

        try {
            $paymentMethod->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payment method deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
