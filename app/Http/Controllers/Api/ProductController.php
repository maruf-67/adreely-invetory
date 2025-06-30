<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of products for the business.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Product::where('business_id', $user->business_id)
            ->with(['category', 'brand', 'unit']);

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by brand
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Search by name or sku
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter low stock products
        if ($request->has('low_stock') && $request->low_stock) {
            $query->whereColumn('quantity', '<=', 'low_stock_threshold');
        }

        $products = $query->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'image' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'required|exists:units,id',
            'description' => 'nullable|string',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'quantity' => 'integer|min:0',
            'low_stock_threshold' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Validate that category, brand, and unit belong to the same business
        if ($request->category_id) {
            $category = Category::find($request->category_id);
            if (!$category || $category->business_id !== $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid category selected'
                ], 400);
            }
        }

        if ($request->brand_id) {
            $brand = Brand::find($request->brand_id);
            if (!$brand || $brand->business_id !== $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid brand selected'
                ], 400);
            }
        }

        $unit = Unit::find($request->unit_id);
        if (!$unit || $unit->business_id !== $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid unit selected'
            ], 400);
        }

        $product = Product::create([
            'business_id' => $user->business_id,
            'name' => $request->name,
            'sku' => $request->sku,
            'image' => $request->image,
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id,
            'unit_id' => $request->unit_id,
            'description' => $request->description,
            'purchase_price' => $request->purchase_price,
            'selling_price' => $request->selling_price,
            'quantity' => $request->quantity ?? 0,
            'low_stock_threshold' => $request->low_stock_threshold ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load(['category', 'brand', 'unit'])
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        
        $product = Product::where('id', $id)
            ->where('business_id', $user->business_id)
            ->with(['category', 'brand', 'unit'])
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        $product = Product::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'image' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'sometimes|required|exists:units,id',
            'description' => 'nullable|string',
            'purchase_price' => 'sometimes|required|numeric|min:0',
            'selling_price' => 'sometimes|required|numeric|min:0',
            'quantity' => 'integer|min:0',
            'low_stock_threshold' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate relationships if provided
        if ($request->has('category_id') && $request->category_id) {
            $category = Category::find($request->category_id);
            if (!$category || $category->business_id !== $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid category selected'
                ], 400);
            }
        }

        if ($request->has('brand_id') && $request->brand_id) {
            $brand = Brand::find($request->brand_id);
            if (!$brand || $brand->business_id !== $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid brand selected'
                ], 400);
            }
        }

        if ($request->has('unit_id')) {
            $unit = Unit::find($request->unit_id);
            if (!$unit || $unit->business_id !== $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid unit selected'
                ], 400);
            }
        }

        $product->update($request->only([
            'name', 'sku', 'image', 'category_id', 'brand_id', 'unit_id', 
            'description', 'purchase_price', 'selling_price', 'quantity', 'low_stock_threshold'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load(['category', 'brand', 'unit'])
        ]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();

        $product = Product::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Check if product has order items
        if ($product->purchaseOrderItems()->count() > 0 || $product->salesOrderItems()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete product that has order history'
            ], 400);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Get low stock products.
     */
    public function lowStock(): JsonResponse
    {
        $user = Auth::user();
        $products = Product::where('business_id', $user->business_id)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->with(['category', 'brand', 'unit'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }
}
