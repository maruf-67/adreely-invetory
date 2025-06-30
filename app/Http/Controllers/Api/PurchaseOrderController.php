<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\InventoryHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of purchase orders for the business.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = PurchaseOrder::where('business_id', $user->business_id)
            ->with(['supplier', 'items.product']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by supplier
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('order_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('order_date', '<=', $request->to_date);
        }

        $orders = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Store a newly created purchase order.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:users,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
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

                // Validate supplier belongs to same business
                $supplier = \App\Models\User::where('id', $request->supplier_id)
                    ->where('business_id', $user->business_id)
                    ->where('user_type', 'supplier')
                    ->first();

                if (!$supplier) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid supplier selected'
                    ], 400);
                }

                // Calculate totals
                $subTotal = 0;
                foreach ($request->items as $item) {
                    $subTotal += $item['quantity_ordered'] * $item['unit_price'];
                }

                $discount = $request->discount ?? 0;
                $totalAmount = $subTotal - $discount;

                // Create purchase order
                $purchaseOrder = PurchaseOrder::create([
                    'business_id' => $user->business_id,
                    'supplier_id' => $request->supplier_id,
                    'order_date' => $request->order_date,
                    'expected_delivery_date' => $request->expected_delivery_date,
                    'status' => 'pending',
                    'sub_total' => $subTotal,
                    'discount' => $discount,
                    'total_amount' => $totalAmount,
                    'paid_amount' => 0,
                    'created_by' => $user->id,
                ]);

                // Create purchase order items
                foreach ($request->items as $item) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $item['product_id'],
                        'quantity_ordered' => $item['quantity_ordered'],
                        'quantity_received' => 0,
                        'unit_price' => $item['unit_price'],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Purchase order created successfully',
                    'data' => $purchaseOrder->load(['supplier', 'items.product'])
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified purchase order.
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        $purchaseOrder = PurchaseOrder::where('business_id', $user->business_id)
            ->with(['supplier', 'items.product', 'creator'])
            ->find($id);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $purchaseOrder
        ]);
    }

    /**
     * Update the specified purchase order.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $purchaseOrder = PurchaseOrder::where('business_id', $user->business_id)->find($id);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
            ], 404);
        }

        if ($purchaseOrder->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update completed purchase order'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'expected_delivery_date' => 'nullable|date',
            'status' => 'in:pending,partial,completed,cancelled',
            'discount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $purchaseOrder->update($request->only([
            'expected_delivery_date', 'status', 'discount'
        ]));

        // Recalculate total if discount changed
        if ($request->has('discount')) {
            $totalAmount = $purchaseOrder->sub_total - $request->discount;
            $purchaseOrder->update(['total_amount' => $totalAmount]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Purchase order updated successfully',
            'data' => $purchaseOrder->load(['supplier', 'items.product'])
        ]);
    }

    /**
     * Receive goods for a purchase order.
     */
    public function receiveGoods(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request, $id) {
                $user = Auth::user();
                $purchaseOrder = PurchaseOrder::where('business_id', $user->business_id)->find($id);

                if (!$purchaseOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Purchase order not found'
                    ], 404);
                }

                foreach ($request->items as $itemData) {
                    $item = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)
                        ->find($itemData['item_id']);

                    if (!$item) {
                        continue;
                    }

                    $quantityToReceive = $itemData['quantity_received'];
                    $maxReceivable = $item->quantity_ordered - $item->quantity_received;

                    if ($quantityToReceive > $maxReceivable) {
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot receive more than ordered quantity for product {$item->product->name}"
                        ], 400);
                    }

                    // Update item received quantity
                    $item->increment('quantity_received', $quantityToReceive);

                    // Update product stock
                    $product = Product::find($item->product_id);
                    $product->increment('quantity', $quantityToReceive);

                    // Record inventory history
                    InventoryHistory::create([
                        'business_id' => $user->business_id,
                        'product_id' => $item->product_id,
                        'user_id' => $user->id,
                        'type' => 'stock-in',
                        'quantity_change' => $quantityToReceive,
                        'reason' => 'Purchase Order Received',
                        'reference_id' => $purchaseOrder->id,
                        'reference_type' => 'App\Models\PurchaseOrder',
                    ]);
                }

                // Update purchase order status
                $allItemsReceived = $purchaseOrder->items()->whereColumn('quantity_received', '<', 'quantity_ordered')->count() === 0;
                $anyItemsReceived = $purchaseOrder->items()->where('quantity_received', '>', 0)->count() > 0;

                if ($allItemsReceived) {
                    $purchaseOrder->update(['status' => 'completed']);
                } elseif ($anyItemsReceived) {
                    $purchaseOrder->update(['status' => 'partial']);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Goods received successfully',
                    'data' => $purchaseOrder->load(['supplier', 'items.product'])
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to receive goods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a purchase order.
     */
    public function cancel($id): JsonResponse
    {
        $user = Auth::user();
        $purchaseOrder = PurchaseOrder::where('business_id', $user->business_id)->find($id);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
            ], 404);
        }

        if ($purchaseOrder->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel completed purchase order'
            ], 400);
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase order cancelled successfully',
            'data' => $purchaseOrder
        ]);
    }
}
