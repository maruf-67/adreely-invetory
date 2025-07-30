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
     * List all products for the authenticated user's business.
     *
     * Features:
     * - Retrieves products with filters (category, brand, search, low stock)
     * - Loads related category, brand, and unit
     *
     * Security considerations:
     * - Only authenticated users can access their business products
     *
     * @param Request $request The request containing filter parameters
     * @return \Illuminate\Http\JsonResponse List of products
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Product::where('business_id', $user->business_id)
            ->with(['category', 'brand', 'unit', 'buyingUnit']);

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
     * Create a new product for the authenticated user's business.
     *
     * Features:
     * - Validates product data
     * - Handles image upload
     * - Validates relationships (category, brand, unit)
     *
     * Security considerations:
     * - Only authenticated users can create products for their business
     * - Input validation prevents malicious data injection
     * - Ensures related entities belong to the business
     *
     * @param Request $request The request containing product data
     * @return \Illuminate\Http\JsonResponse Created product or error message
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'image' => 'nullable|file|image|max:2048',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'required|exists:units,id',
            'buying_unit_id' => 'nullable|exists:units,id',
            'description' => 'nullable|string',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
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
            if (!$category || $category->business_id != $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid category selected'
                ], 400);
            }
        }

        if ($request->brand_id) {
            $brand = Brand::find($request->brand_id);
            if (!$brand || $brand->business_id != $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid brand selected'
                ], 400);
            }
        }

        $unit = Unit::find($request->unit_id);
        if (!$unit || $unit->business_id != $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid unit selected'
            ], 400);
        }

        if ($request->buying_unit_id) {
            $buyingUnit = Unit::find($request->buying_unit_id);
            if (!$buyingUnit || $buyingUnit->business_id != $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid buying unit selected'
                ], 400);
            }
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/products'), $imageName);
            $imagePath = 'uploads/products/' . $imageName;
        }

        $product = Product::create([
            'business_id' => $user->business_id,
            'name' => $request->name,
            'sku' => $request->sku,
            'image' => $imagePath,
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id,
            'unit_id' => $request->unit_id,
            'buying_unit_id' => $request->buying_unit_id,
            'description' => $request->description,
            'purchase_price' => $request->purchase_price,
            'selling_price' => $request->selling_price,
            'quantity' => 0, // Always start with 0, quantity comes from purchases
            'low_stock_threshold' => $request->low_stock_threshold ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load(['category', 'brand', 'unit', 'buyingUnit'])
        ], 201);
    }

    /**
     * Retrieve a specific product by ID for the authenticated user's business.
     *
     * Features:
     * - Loads a product by ID with related data
     *
     * Security considerations:
     * - Only authenticated users can access their business products
     *
     * @param int $id The product ID
     * @return \Illuminate\Http\JsonResponse Product data or error message
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        
        $product = Product::where('id', $id)
            ->where('business_id', $user->business_id)
            ->with(['category', 'brand', 'unit', 'buyingUnit'])
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
     * Update a specific product for the authenticated user's business.
     *
     * Features:
     * - Validates and updates product data
     * - Handles image upload and relationship validation
     *
     * Security considerations:
     * - Only authenticated users can update their business products
     * - Input validation prevents malicious data injection
     * - Ensures related entities belong to the business
     *
     * @param Request $request The request containing product updates
     * @param int $id The product ID
     * @return \Illuminate\Http\JsonResponse Updated product or error message
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
            'image' => 'nullable|file|image|max:2048',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'sometimes|required|exists:units,id',
            'buying_unit_id' => 'nullable|exists:units,id',
            'description' => 'nullable|string',
            'purchase_price' => 'sometimes|required|numeric|min:0',
            'selling_price' => 'sometimes|required|numeric|min:0',
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
            if (!$category || $category->business_id != $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid category selected'
                ], 400);
            }
        }

        if ($request->has('brand_id') && $request->brand_id) {
            $brand = Brand::find($request->brand_id);
            if (!$brand || $brand->business_id != $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid brand selected'
                ], 400);
            }
        }

        if ($request->has('unit_id')) {
            $unit = Unit::find($request->unit_id);
            if (!$unit || $unit->business_id != $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid unit selected'
                ], 400);
            }
        }

        if ($request->has('buying_unit_id')) {
            $buyingUnit = Unit::find($request->buying_unit_id);
            if (!$buyingUnit || $buyingUnit->business_id != $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid buying unit selected'
                ], 400);
            }
        }

        $updateData = $request->only([
            'name', 'sku', 'category_id', 'brand_id', 'unit_id', 'buying_unit_id',
            'description', 'purchase_price', 'selling_price', 'low_stock_threshold'
        ]);

        // Handle image upload and delete previous image
        if ($request->hasFile('image')) {
            // Delete previous image if exists
            if ($product->image && file_exists(public_path($product->image))) {
                unlink(public_path($product->image));
            }
            
            // Upload new image
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/products'), $imageName);
            $updateData['image'] = 'uploads/products/' . $imageName;
        }

        $product->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load(['category', 'brand', 'unit', 'buyingUnit'])
        ]);
    }

    /**
     * Delete a specific product from the authenticated user's business.
     *
     * Features:
     * - Deletes a product if not used in order history
     * - Handles image deletion
     *
     * Security considerations:
     * - Only authenticated users can delete their business products
     * - Prevents deletion if product is in use by orders
     *
     * @param int $id The product ID
     * @return \Illuminate\Http\JsonResponse Success or error message
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

        // Check if product has order items or inventory history
        if ($product->purchaseOrderItems()->count() > 0 || 
            $product->salesOrderItems()->count() > 0 || 
            $product->inventoryHistories()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete product that has transaction history'
            ], 400);
        }

        // Delete product image if exists
        if ($product->image && file_exists(public_path($product->image))) {
            unlink(public_path($product->image));
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * List low stock products for the authenticated user's business.
     *
     * Features:
     * - Retrieves products with quantity below or equal to low stock threshold
     * - Loads related category, brand, and unit
     *
     * Security considerations:
     * - Only authenticated users can access their business products
     *
     * @return \Illuminate\Http\JsonResponse List of low stock products
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

     /**
     * Get the stock movement history for a product.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function stockHistory($id): JsonResponse
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
        $history = $product->inventoryHistories()->with('user')->orderByDesc('created_at')->get();
        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Adjust the stock for a product (manual correction).
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function adjustStock(Request $request, $id): JsonResponse
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
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'reason' => 'required|string|min:10|max:255', // Minimum 10 characters for meaningful reason
        ]);
        $before = $product->quantity;
        $after = $validated['quantity'];
        $change = $after - $before;
        $product->quantity = $after;
        $product->save();
        // Log inventory history
        $product->inventoryHistories()->create([
            'business_id' => $product->business_id,
            'user_id' => $user->id,
            'type' => 'adjustment',
            'quantity_change' => $change,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => "Manual Adjustment: {$validated['reason']}",
            'reference_type' => null,
            'reference_id' => null,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Stock adjusted successfully',
            'data' => $product->fresh()
        ]);
    }

    /**
     * List only products that are in stock (quantity > 0) for the authenticated user's business.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function inStock(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Product::where('business_id', $user->business_id)
            ->where('quantity', '>', 0)
            ->with(['category', 'brand', 'unit', 'buyingUnit']);

        // Optional: allow filtering by category, brand, or search
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

}
