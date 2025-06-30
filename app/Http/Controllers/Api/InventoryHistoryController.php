<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryHistory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryHistoryController extends Controller
{
    /**
     * Display a listing of inventory histories for the business.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = InventoryHistory::where('business_id', $user->business_id)
            ->with(['product', 'user']);

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $histories = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $histories
        ]);
    }

    /**
     * Manual stock adjustment.
     */
    public function stockAdjustment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity_change' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $user = Auth::user();

                // Validate product belongs to same business
                $product = Product::where('id', $request->product_id)
                    ->where('business_id', $user->business_id)
                    ->first();

                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found'
                    ], 404);
                }

                $quantityChange = $request->quantity_change;

                // Check if we have enough stock for negative adjustments
                if ($quantityChange < 0 && $product->quantity < abs($quantityChange)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock for adjustment. Available: ' . $product->quantity
                    ], 400);
                }

                // Update product stock
                $product->increment('quantity', $quantityChange);

                // Record inventory history
                $inventoryHistory = InventoryHistory::create([
                    'business_id' => $user->business_id,
                    'product_id' => $request->product_id,
                    'user_id' => $user->id,
                    'type' => 'adjustment',
                    'quantity_change' => $quantityChange,
                    'reason' => $request->reason,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Stock adjustment completed successfully',
                    'data' => [
                        'inventory_history' => $inventoryHistory->load(['product', 'user']),
                        'updated_product' => $product->fresh()
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock movement report for a product.
     */
    public function productStockReport($productId, Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Validate product
        $product = Product::where('id', $productId)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $query = InventoryHistory::where('business_id', $user->business_id)
            ->where('product_id', $productId)
            ->with(['user']);

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $movements = $query->latest()->get();

        $totalStockIn = $movements->where('type', 'stock-in')->sum('quantity_change');
        $totalStockOut = abs($movements->where('type', 'stock-out')->sum('quantity_change'));
        $totalAdjustments = $movements->where('type', 'adjustment')->sum('quantity_change');

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
                'current_stock' => $product->quantity,
                'total_stock_in' => $totalStockIn,
                'total_stock_out' => $totalStockOut,
                'total_adjustments' => $totalAdjustments,
                'movements' => $movements
            ]
        ]);
    }

    /**
     * Get inventory analytics.
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = InventoryHistory::where('business_id', $user->business_id);

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $totalMovements = $query->count();
        $stockInMovements = $query->where('type', 'stock-in')->sum('quantity_change');
        $stockOutMovements = abs($query->where('type', 'stock-out')->sum('quantity_change'));
        $adjustmentMovements = $query->where('type', 'adjustment')->sum('quantity_change');

        $typeBreakdown = $query->selectRaw('type, COUNT(*) as count, SUM(ABS(quantity_change)) as total_quantity')
            ->groupBy('type')
            ->get();

        $dailyMovements = $query->selectRaw('DATE(created_at) as date, COUNT(*) as movements, SUM(ABS(quantity_change)) as total_quantity')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_movements' => $totalMovements,
                'total_stock_in' => $stockInMovements,
                'total_stock_out' => $stockOutMovements,
                'total_adjustments' => $adjustmentMovements,
                'type_breakdown' => $typeBreakdown,
                'daily_movements' => $dailyMovements
            ]
        ]);
    }

    /**
     * Get low stock products.
     */
    public function lowStockAlert(): JsonResponse
    {
        $user = Auth::user();
        
        $lowStockProducts = Product::where('business_id', $user->business_id)
            ->whereRaw('quantity <= low_stock_threshold')
            ->with(['category', 'brand', 'unit'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lowStockProducts,
            'count' => $lowStockProducts->count()
        ]);
    }

    /**
     * Get stock valuation report.
     */
    public function stockValuation(): JsonResponse
    {
        $user = Auth::user();
        
        $products = Product::where('business_id', $user->business_id)
            ->with(['category', 'brand'])
            ->get();

        $totalPurchaseValue = 0;
        $totalSellingValue = 0;
        $categoryBreakdown = [];

        foreach ($products as $product) {
            $purchaseValue = $product->quantity * $product->purchase_price;
            $sellingValue = $product->quantity * $product->selling_price;
            
            $totalPurchaseValue += $purchaseValue;
            $totalSellingValue += $sellingValue;

            $categoryName = $product->category ? $product->category->name : 'Uncategorized';
            
            if (!isset($categoryBreakdown[$categoryName])) {
                $categoryBreakdown[$categoryName] = [
                    'purchase_value' => 0,
                    'selling_value' => 0,
                    'quantity' => 0,
                    'products_count' => 0
                ];
            }
            
            $categoryBreakdown[$categoryName]['purchase_value'] += $purchaseValue;
            $categoryBreakdown[$categoryName]['selling_value'] += $sellingValue;
            $categoryBreakdown[$categoryName]['quantity'] += $product->quantity;
            $categoryBreakdown[$categoryName]['products_count']++;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_purchase_value' => round($totalPurchaseValue, 2),
                'total_selling_value' => round($totalSellingValue, 2),
                'potential_profit' => round($totalSellingValue - $totalPurchaseValue, 2),
                'total_products' => $products->count(),
                'total_quantity' => $products->sum('quantity'),
                'category_breakdown' => $categoryBreakdown
            ]
        ]);
    }
}
