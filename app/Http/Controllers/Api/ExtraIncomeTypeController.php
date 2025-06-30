<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExtraIncomeType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ExtraIncomeTypeController extends Controller
{
    /**
     * Display a listing of extra income types for the authenticated user's business.
     */
    public function index(Request $request): JsonResponse
    {
        $businessId = Auth::user()->business_id;
        
        $query = ExtraIncomeType::where('business_id', $businessId)
            ->withCount('extraIncomes')
            ->withSum('extraIncomes', 'amount');

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('name');

        $extraIncomeTypes = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $extraIncomeTypes,
            'message' => 'Extra income types retrieved successfully'
        ]);
    }

    /**
     * Store a newly created extra income type.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255'
            ]);

            $businessId = Auth::user()->business_id;

            // Check for duplicate name within the same business
            $existingType = ExtraIncomeType::where('business_id', $businessId)
                ->where('name', $validated['name'])
                ->first();

            if ($existingType) {
                return response()->json([
                    'success' => false,
                    'message' => 'An extra income type with this name already exists'
                ], 422);
            }

            $validated['business_id'] = $businessId;

            $extraIncomeType = ExtraIncomeType::create($validated);

            return response()->json([
                'success' => true,
                'data' => $extraIncomeType,
                'message' => 'Extra income type created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Display the specified extra income type.
     */
    public function show(ExtraIncomeType $extraIncomeType): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($extraIncomeType->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Extra income type not found or access denied'
            ], 404);
        }

        $extraIncomeType->loadCount('extraIncomes');
        $extraIncomeType->loadSum('extraIncomes', 'amount');
        $extraIncomeType->load(['extraIncomes' => function ($query) {
            $query->orderBy('date', 'desc')->limit(5);
        }]);

        return response()->json([
            'success' => true,
            'data' => $extraIncomeType,
            'message' => 'Extra income type retrieved successfully'
        ]);
    }

    /**
     * Update the specified extra income type.
     */
    public function update(Request $request, ExtraIncomeType $extraIncomeType): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($extraIncomeType->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Extra income type not found or access denied'
            ], 404);
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255'
            ]);

            // Check for duplicate name within the same business (excluding current type)
            if (isset($validated['name'])) {
                $existingType = ExtraIncomeType::where('business_id', $businessId)
                    ->where('name', $validated['name'])
                    ->where('id', '!=', $extraIncomeType->id)
                    ->first();

                if ($existingType) {
                    return response()->json([
                        'success' => false,
                        'message' => 'An extra income type with this name already exists'
                    ], 422);
                }
            }

            $extraIncomeType->update($validated);

            return response()->json([
                'success' => true,
                'data' => $extraIncomeType,
                'message' => 'Extra income type updated successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Remove the specified extra income type.
     */
    public function destroy(ExtraIncomeType $extraIncomeType): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($extraIncomeType->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Extra income type not found or access denied'
            ], 404);
        }

        // Check if income type has any extra incomes
        if ($extraIncomeType->extraIncomes()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete extra income type with existing extra incomes'
            ], 422);
        }

        $extraIncomeType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Extra income type deleted successfully'
        ]);
    }
}
