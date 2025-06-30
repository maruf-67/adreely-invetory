<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InvestorController extends Controller
{
    /**
     * Display a listing of investors for the authenticated user's business.
     */
    public function index(Request $request): JsonResponse
    {
        $businessId = Auth::user()->business_id;
        
        $query = Investor::where('business_id', $businessId)
            ->withCount('investments')
            ->withSum('investments', 'amount');

        // Search by name or contact info
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_info', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name');

        $investors = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $investors,
            'message' => 'Investors retrieved successfully'
        ]);
    }

    /**
     * Store a newly created investor.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'contact_info' => 'nullable|string|max:1000'
            ]);

            $businessId = Auth::user()->business_id;

            $validated['business_id'] = $businessId;

            $investor = Investor::create($validated);

            return response()->json([
                'success' => true,
                'data' => $investor,
                'message' => 'Investor created successfully'
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
     * Display the specified investor.
     */
    public function show(Investor $investor): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($investor->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Investor not found or access denied'
            ], 404);
        }

        $investor->loadCount('investments');
        $investor->loadSum('investments', 'amount');
        $investor->load(['investments' => function ($query) {
            $query->orderBy('investment_date', 'desc')->limit(5);
        }]);

        return response()->json([
            'success' => true,
            'data' => $investor,
            'message' => 'Investor retrieved successfully'
        ]);
    }

    /**
     * Update the specified investor.
     */
    public function update(Request $request, Investor $investor): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($investor->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Investor not found or access denied'
            ], 404);
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'contact_info' => 'sometimes|nullable|string|max:1000'
            ]);

            $investor->update($validated);

            return response()->json([
                'success' => true,
                'data' => $investor,
                'message' => 'Investor updated successfully'
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
     * Remove the specified investor.
     */
    public function destroy(Investor $investor): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($investor->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Investor not found or access denied'
            ], 404);
        }

        // Check if investor has any investments
        if ($investor->investments()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete investor with existing investments'
            ], 422);
        }

        $investor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Investor deleted successfully'
        ]);
    }
}
