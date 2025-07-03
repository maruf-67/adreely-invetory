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
     * List all payment methods for the authenticated user's business.
     *
     * Features:
     * - Retrieves payment methods with optional filters (active, type)
     * - Returns payment method data in JSON format
     *
     * Security considerations:
     * - Only authenticated users can access their business payment methods
     *
     * @param Request $request The request containing filter parameters
     * @return \Illuminate\Http\JsonResponse List of payment methods
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
     * Create a new payment method for the authenticated user's business.
     *
     * Features:
     * - Validates payment method data
     * - Creates a new payment method record
     *
     * Security considerations:
     * - Only authenticated users can create payment methods for their business
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing payment method data
     * @return \Illuminate\Http\JsonResponse Created payment method or error message
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
            'account_number' => 'nullable|string|max:100',
            'details' => 'nullable|string|max:500', // Changed to string for flexibility
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
                'account_number' => $request->account_number,
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
     * Retrieve a specific payment method by ID for the authenticated user's business.
     *
     * Features:
     * - Loads a payment method by ID if it belongs to the user's business
     *
     * Security considerations:
     * - Only authenticated users can access their business payment methods
     *
     * @param int $id The payment method ID
     * @return \Illuminate\Http\JsonResponse Payment method data or error message
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
     * Update a specific payment method for the authenticated user's business.
     *
     * Features:
     * - Validates and updates payment method data
     *
     * Security considerations:
     * - Only authenticated users can update their business payment methods
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing payment method updates
     * @param int $id The payment method ID
     * @return \Illuminate\Http\JsonResponse Updated payment method or error message
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
            'type' => 'nullable|string|max:50',
            'account_number' => 'nullable|string|max:100',
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
                'account_number' => $request->get('account_number', $paymentMethod->account_number),
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
     * Delete a specific payment method from the authenticated user's business.
     *
     * Features:
     * - Deletes a payment method if not used in transactions
     * - Returns confirmation message
     *
     * Security considerations:
     * - Only authenticated users can delete their business payment methods
     * - Prevents deletion if payment method is in use
     *
     * @param int $id The payment method ID
     * @return \Illuminate\Http\JsonResponse Success or error message
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
