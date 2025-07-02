<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseShipment;
use App\Models\PurchaseShipmentItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    /**
     * List all purchase orders for the authenticated user's business.
     *
     * Features:
     * - Retrieves purchase orders with filters (status, date range, supplier)
     * - Supports pagination
     * - Loads related supplier, items, and shipments
     *
     * Security considerations:
     * - Only authenticated users can access their business purchase orders
     *
     * @param Request $request The request containing filter parameters
     * @return \Illuminate\Http\JsonResponse List of purchase orders
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = PurchaseOrder::where('business_id', $user->business_id)
            ->with(['supplier', 'items.product', 'shipments']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('order_date', [$request->start_date, $request->end_date]);
        }

        // Filter by supplier
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $purchaseOrders = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $purchaseOrders
        ]);
    }

    /**
     * Create a new purchase order for the authenticated user's business.
     *
     * Features:
     * - Validates purchase order and item data
     * - Verifies supplier and calculates totals
     * - Creates purchase order and items in a transaction
     *
     * Security considerations:
     * - Only authenticated users can create purchase orders for their business
     * - Input validation prevents malicious data injection
     * - Ensures supplier belongs to the business and has correct role
     *
     * @param Request $request The request containing purchase order data
     * @return \Illuminate\Http\JsonResponse Created purchase order or error message
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:users,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
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

                // Verify supplier belongs to the same business and has supplier role
                $supplier = User::where('id', $request->supplier_id)
                    ->where('business_id', $user->business_id)
                    ->where('user_type', 'supplier')
                    ->first();

                if (!$supplier) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid supplier'
                    ], 400);
                }

                // Calculate totals
                $subTotal = 0;
                foreach ($request->items as $item) {
                    $subTotal += $item['quantity_ordered'] * $item['unit_price'];
                }

                $discount = $request->get('discount', 0);
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
                    'notes' => $request->notes,
                    'created_by' => $user->id,
                ]);

                // Generate order number
                $purchaseOrder->generateOrderNumber();

                // Create purchase order items
                foreach ($request->items as $itemData) {
                    $totalPrice = $itemData['quantity_ordered'] * $itemData['unit_price'];
                    
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $itemData['product_id'],
                        'quantity_ordered' => $itemData['quantity_ordered'],
                        'unit_price' => $itemData['unit_price'],
                        'total_price' => $totalPrice,
                        'notes' => $itemData['notes'] ?? null,
                        'created_by' => $user->id,
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
     * Retrieve a specific purchase order by ID for the authenticated user's business.
     *
     * Features:
     * - Loads a purchase order by ID with related data
     *
     * Security considerations:
     * - Only authenticated users can access their business purchase orders
     *
     * @param int $id The purchase order ID
     * @return \Illuminate\Http\JsonResponse Purchase order data or error message
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        $purchaseOrder = PurchaseOrder::where('business_id', $user->business_id)
            ->with(['supplier', 'items.product', 'shipments.items', 'payments.paymentMethod'])
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

        if ($purchaseOrder->status !== 'pending' && $purchaseOrder->status !== 'partial') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update purchase order that is not pending or partial'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $purchaseOrder->update([
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes,
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order updated successfully',
                'data' => $purchaseOrder->fresh()->load(['supplier', 'items.product'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Receive goods with multiple shipments support.
     */
    public function receiveShipment(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'received_date' => 'required|date',
            'shipment_number' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
            'notes' => 'nullable|string',
            'payment_amount' => 'nullable|numeric|min:0',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'payment_notes' => 'nullable|string',
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
                $purchaseOrder = PurchaseOrder::where('business_id', $user->business_id)
                    ->with('items')
                    ->find($id);

                if (!$purchaseOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Purchase order not found'
                    ], 404);
                }

                // Generate shipment number if not provided
                $shipmentNumber = $request->shipment_number ?? 
                    'SH-' . $purchaseOrder->id . '-' . 
                    str_pad($purchaseOrder->shipments()->count() + 1, 3, '0', STR_PAD_LEFT);

                // Create shipment
                $shipment = PurchaseShipment::create([
                    'business_id' => $user->business_id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'shipment_number' => $shipmentNumber,
                    'received_date' => $request->received_date,
                    'notes' => $request->notes,
                    'created_by' => $user->id,
                ]);

                $shipmentTotal = 0;

                // Process each item in the shipment
                foreach ($request->items as $itemData) {
                    $purchaseOrderItem = PurchaseOrderItem::find($itemData['purchase_order_item_id']);
                    
                    if (!$purchaseOrderItem || $purchaseOrderItem->purchase_order_id !== $purchaseOrder->id) {
                        throw new \Exception('Invalid purchase order item');
                    }

                    $quantityReceived = $itemData['quantity_received'];
                    $unitPrice = $itemData['unit_price'];
                    $totalPrice = $quantityReceived * $unitPrice;
                    $shipmentTotal += $totalPrice;

                    // Create shipment item
                    PurchaseShipmentItem::create([
                        'shipment_id' => $shipment->id,
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'quantity_received' => $quantityReceived,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'notes' => $itemData['notes'] ?? null,
                    ]);

                    // Update product stock
                    $product = Product::find($purchaseOrderItem->product_id);
                    $quantityBefore = $product->quantity;
                    $product->increment('quantity', $quantityReceived);

                    // Record inventory history
                    InventoryHistory::createRecord(
                        $user->business_id,
                        $purchaseOrderItem->product_id,
                        $user->id,
                        'stock-in',
                        $quantityReceived,
                        $quantityBefore,
                        "Shipment Received: {$shipmentNumber}",
                        $shipment
                    );
                }

                // Update shipment total amount
                $shipment->update(['total_amount' => $shipmentTotal]);

                // Update purchase order received amount
                $purchaseOrder->updateReceivedAmount();

                // Handle payment if provided
                if ($request->has('payment_amount') && $request->payment_amount > 0) {
                    Payment::create([
                        'business_id' => $user->business_id,
                        'paymentable_type' => PurchaseOrder::class,
                        'paymentable_id' => $purchaseOrder->id,
                        'payment_method_id' => $request->payment_method_id,
                        'amount' => $request->payment_amount,
                        'transaction_date' => $request->received_date,
                        'details' => $request->payment_notes,
                        'status' => 'clear',
                        'created_by' => $user->id,
                    ]);

                    // Update purchase order paid amount
                    $purchaseOrder->updatePaidAmount();
                }

                // Update purchase order status
                $purchaseOrder->updateStatus();

                return response()->json([
                    'success' => true,
                    'message' => 'Shipment received successfully',
                    'data' => [
                        'shipment' => $shipment->load('items.purchaseOrderItem.product'),
                        'purchase_order' => $purchaseOrder->fresh()->load(['supplier', 'items.product', 'shipments'])
                    ]
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to receive shipment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shipments for a purchase order.
     */
    public function getShipments($id): JsonResponse
    {
        $user = Auth::user();
        $purchaseOrder = PurchaseOrder::where('business_id', $user->business_id)->find($id);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
            ], 404);
        }

        $shipments = $purchaseOrder->shipments()
            ->with(['items.purchaseOrderItem.product'])
            ->latest('received_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shipments
        ]);
    }

    /**
     * Add payment to purchase order.
     */
    public function addPayment(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'details' => 'nullable|string',
            'reference_number' => 'nullable|string',
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
            $purchaseOrder = PurchaseOrder::where('business_id', $user->business_id)->find($id);

            if (!$purchaseOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order not found'
                ], 404);
            }

            // Check if payment amount doesn't exceed due amount
            $dueAmount = $purchaseOrder->total_amount - $purchaseOrder->paid_amount;
            if ($request->amount > $dueAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds due amount'
                ], 400);
            }

            $payment = Payment::create([
                'business_id' => $user->business_id,
                'paymentable_type' => PurchaseOrder::class,
                'paymentable_id' => $purchaseOrder->id,
                'payment_method_id' => $request->payment_method_id,
                'amount' => $request->amount,
                'transaction_date' => $request->transaction_date,
                'details' => $request->details,
                'reference_number' => $request->reference_number,
                'status' => 'clear',
                'created_by' => $user->id,
            ]);

            // Update purchase order paid amount
            $purchaseOrder->updatePaidAmount();

            return response()->json([
                'success' => true,
                'message' => 'Payment added successfully',
                'data' => [
                    'payment' => $payment->load('paymentMethod'),
                    'purchase_order' => $purchaseOrder->fresh()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add payment',
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

        if ($purchaseOrder->shipments()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel purchase order with received shipments'
            ], 400);
        }

        try {
            $purchaseOrder->update([
                'status' => 'cancelled',
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order cancelled successfully',
                'data' => $purchaseOrder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update purchase order items for the specified purchase order.
     *
     * Features:
     * - Updates existing items (quantity, unit price, notes)
     * - Adds new items to the purchase order
     * - Removes items not included in the request
     * - Recalculates purchase order totals automatically
     * - Supports bulk operations within a transaction
     * - Validates all item data before making changes
     *
     * Business Rules:
     * - Only allows updates to orders with 'pending' or 'partial' status
     * - Cannot modify items that have been partially or fully received
     * - Prevents deletion of items with received quantities
     * - Automatically recalculates order totals based on item changes
     * - Maintains audit trail with user tracking
     *
     * Security considerations:
     * - Only authenticated users can update their business purchase orders
     * - Validates product existence and business ownership
     * - Prevents unauthorized access to other business data
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing items data
     * @param int $id The purchase order ID
     * @return \Illuminate\Http\JsonResponse Updated purchase order with items or error message
     *
     * Request format:
     * {
     *   "items": [
     *     {
     *       "id": 1,                    // Optional: existing item ID for updates
     *       "product_id": 5,            // Required: product ID
     *       "quantity_ordered": 10,     // Required: quantity to order
     *       "unit_price": 25.50,        // Required: price per unit
     *       "notes": "Special instructions" // Optional: item notes
     *     }
     *   ],
     *   "discount": 50.00             // Optional: order discount
     * }
     */
    public function updateItems(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:purchase_order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:1000',
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
            return DB::transaction(function () use ($request, $id) {
                $user = Auth::user();
                $purchaseOrder = PurchaseOrder::where('business_id', $user->business_id)
                    ->with(['items', 'supplier'])
                    ->find($id);

                if (!$purchaseOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Purchase order not found'
                    ], 404);
                }

                // Check if purchase order can be modified
                if (!in_array($purchaseOrder->status, ['pending', 'partial'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot update items for purchase order with status: ' . $purchaseOrder->status
                    ], 400);
                }

                // Verify all products belong to the business
                $productIds = collect($request->items)->pluck('product_id');
                $validProducts = Product::where('business_id', $user->business_id)
                    ->whereIn('id', $productIds)
                    ->pluck('id');

                if ($productIds->diff($validProducts)->count() > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'One or more products do not belong to your business'
                    ], 400);
                }

                // Get existing items IDs that will be updated
                $updatingItemIds = collect($request->items)
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                // Check if any existing items have received quantities
                $itemsWithReceived = $purchaseOrder->items()
                    ->whereIn('id', $updatingItemIds)
                    ->where('quantity_received', '>', 0)
                    ->count();

                if ($itemsWithReceived > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot update items that have already been partially or fully received'
                    ], 400);
                }

                // Identify items to delete (existing items not in the request)
                $itemsToDelete = $purchaseOrder->items()
                    ->whereNotIn('id', $updatingItemIds)
                    ->where('quantity_received', 0) // Only delete items with no received quantity
                    ->get();

                // Check if any items to delete have received quantities
                $itemsToDeleteWithReceived = $purchaseOrder->items()
                    ->whereNotIn('id', $updatingItemIds)
                    ->where('quantity_received', '>', 0)
                    ->count();

                if ($itemsToDeleteWithReceived > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot remove items that have already been partially or fully received'
                    ], 400);
                }

                // Delete items that are no longer needed
                foreach ($itemsToDelete as $item) {
                    $item->delete();
                }

                $subTotal = 0;

                // Process each item (update existing or create new)
                foreach ($request->items as $itemData) {
                    $totalPrice = $itemData['quantity_ordered'] * $itemData['unit_price'];
                    $subTotal += $totalPrice;

                    if (isset($itemData['id']) && $itemData['id']) {
                        // Update existing item
                        $existingItem = PurchaseOrderItem::where('id', $itemData['id'])
                            ->where('purchase_order_id', $purchaseOrder->id)
                            ->first();

                        if ($existingItem) {
                            $existingItem->update([
                                'product_id' => $itemData['product_id'],
                                'quantity_ordered' => $itemData['quantity_ordered'],
                                'unit_price' => $itemData['unit_price'],
                                'total_price' => $totalPrice,
                                'notes' => $itemData['notes'] ?? null,
                                'updated_by' => $user->id,
                            ]);
                        }
                    } else {
                        // Create new item
                        PurchaseOrderItem::create([
                            'purchase_order_id' => $purchaseOrder->id,
                            'product_id' => $itemData['product_id'],
                            'quantity_ordered' => $itemData['quantity_ordered'],
                            'unit_price' => $itemData['unit_price'],
                            'total_price' => $totalPrice,
                            'notes' => $itemData['notes'] ?? null,
                            'created_by' => $user->id,
                        ]);
                    }
                }

                // Update purchase order totals
                $discount = $request->get('discount', $purchaseOrder->discount);
                $totalAmount = $subTotal - $discount;

                $purchaseOrder->update([
                    'sub_total' => $subTotal,
                    'discount' => $discount,
                    'total_amount' => $totalAmount,
                    'updated_by' => $user->id,
                ]);

                // Reload purchase order with fresh data
                $purchaseOrder = $purchaseOrder->fresh(['supplier', 'items.product']);

                return response()->json([
                    'success' => true,
                    'message' => 'Purchase order items updated successfully',
                    'data' => $purchaseOrder
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase order items',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
