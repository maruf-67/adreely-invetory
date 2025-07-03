<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesShipment;
use App\Models\SalesShipmentItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesOrderController extends Controller
{
    /**
     * List all sales orders for the authenticated user's business.
     *
     * Features:
     * - Retrieves sales orders with filters (status, date range, customer)
     * - Supports pagination
     * - Loads related customer, items, and shipments
     *
     * Security considerations:
     * - Only authenticated users can access their business sales orders
     *
     * @param Request $request The request containing filter parameters
     * @return \Illuminate\Http\JsonResponse List of sales orders
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = SalesOrder::where('business_id', $user->business_id)
            ->with(['customer', 'items.product', 'shipments']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('order_date', [$request->start_date, $request->end_date]);
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $salesOrders = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $salesOrders
        ]);
    }

    /**
     * Create a new sales order for the authenticated user's business.
     *
     * Features:
     * - Validates sales order and item data
     * - Verifies customer and calculates totals with tax
     * - Creates sales order and items in a transaction
     * - Generates order number automatically
     *
     * Security considerations:
     * - Only authenticated users can create sales orders for their business
     * - Input validation prevents malicious data injection
     * - Ensures customer belongs to the business and has correct role
     *
     * @param Request $request The request containing sales order data
     * @return \Illuminate\Http\JsonResponse Created sales order or error message
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:users,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
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

                // Verify customer belongs to the same business and has customer role
                $customer = User::where('id', $request->customer_id)
                    ->where('business_id', $user->business_id)
                    ->whereIn('user_type', ['customer', 'retailer', 'dealer', 'wholesaler'])
                    ->first();

                if (!$customer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid customer'
                    ], 400);
                }

                // Calculate totals
                $subTotal = 0;
                foreach ($request->items as $item) {
                    $subTotal += $item['quantity_ordered'] * $item['unit_price'];
                }

                $discount = $request->get('discount', 0);
                $discountType = $request->get('discount_type', 'fixed');
                
                // Calculate discount amount
                if ($discountType === 'percentage') {
                    $discountAmount = ($subTotal * $discount) / 100;
                } else {
                    $discountAmount = $discount;
                }

                $afterDiscount = $subTotal - $discountAmount;
                $taxRate = $request->get('tax_rate', 0);
                $taxAmount = ($afterDiscount * $taxRate) / 100;
                $totalAmount = $afterDiscount + $taxAmount;

                // Create sales order
                $salesOrder = SalesOrder::create([
                    'business_id' => $user->business_id,
                    'customer_id' => $request->customer_id,
                    'order_date' => $request->order_date,
                    'expected_delivery_date' => $request->expected_delivery_date,
                    'status' => 'draft',
                    'sub_total' => $subTotal,
                    'discount' => $discountAmount,
                    'discount_type' => $discountType,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'notes' => $request->notes,
                    'created_by' => $user->id,
                ]);

                // Generate order number
                $salesOrder->generateOrderNumber();

                // Create sales order items
                foreach ($request->items as $itemData) {
                    $totalPrice = $itemData['quantity_ordered'] * $itemData['unit_price'];
                    
                    SalesOrderItem::create([
                        'sales_order_id' => $salesOrder->id,
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
                    'message' => 'Sales order created successfully',
                    'data' => $salesOrder->load(['customer', 'items.product'])
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retrieve a specific sales order by ID for the authenticated user's business.
     *
     * Features:
     * - Loads a sales order by ID with related data
     *
     * Security considerations:
     * - Only authenticated users can access their business sales orders
     *
     * @param int $id The sales order ID
     * @return \Illuminate\Http\JsonResponse Sales order data or error message
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        $salesOrder = SalesOrder::where('business_id', $user->business_id)
            ->with(['customer', 'items.product', 'shipments.items', 'payments.paymentMethod'])
            ->find($id);

        if (!$salesOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Sales order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $salesOrder
        ]);
    }

    /**
     * Update the specified sales order.
     *
     * Features:
     * - Updates basic sales order information
     * - Only allows updates to orders in draft or confirmed status
     *
     * Security considerations:
     * - Only authenticated users can update their business sales orders
     *
     * @param Request $request The request containing sales order updates
     * @param int $id The sales order ID
     * @return \Illuminate\Http\JsonResponse Updated sales order or error message
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $salesOrder = SalesOrder::where('business_id', $user->business_id)->find($id);

        if (!$salesOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Sales order not found'
            ], 404);
        }

        if (!in_array($salesOrder->status, ['draft', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update sales order that is not draft or confirmed'
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
            $salesOrder->update([
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes,
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sales order updated successfully',
                'data' => $salesOrder->fresh()->load(['customer', 'items.product'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm a sales order.
     *
     * Features:
     * - Changes status from draft to confirmed
     * - Generates invoice number
     * - Validates stock availability
     *
     * @param int $id The sales order ID
     * @return \Illuminate\Http\JsonResponse Confirmed sales order or error message
     */
    public function confirm($id): JsonResponse
    {
        $user = Auth::user();
        $salesOrder = SalesOrder::where('business_id', $user->business_id)->find($id);

        if (!$salesOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Sales order not found'
            ], 404);
        }

        if ($salesOrder->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Sales order is not in draft status'
            ], 400);
        }

        try {
            // Check stock availability
            foreach ($salesOrder->items as $item) {
                if ($item->product->quantity < $item->quantity_ordered) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for product: {$item->product->name}"
                    ], 400);
                }
            }

            $salesOrder->update([
                'status' => 'confirmed',
                'updated_by' => $user->id,
            ]);

            // Generate invoice number
            $salesOrder->generateInvoiceNumber();

            return response()->json([
                'success' => true,
                'message' => 'Sales order confirmed successfully',
                'data' => $salesOrder->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ship goods with shipment support.
     *
     * Features:
     * - Creates shipment records
     * - Updates inventory automatically
     * - Records inventory history
     * - Supports partial shipments
     *
     * @param Request $request The request containing shipment data
     * @param int $id The sales order ID
     * @return \Illuminate\Http\JsonResponse Shipment data or error message
     */
    public function ship(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shipped_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:shipped_date',
            'shipment_number' => 'nullable|string',
            'tracking_number' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.sales_order_item_id' => 'required|exists:sales_order_items,id',
            'items.*.quantity_shipped' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
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
            return DB::transaction(function () use ($request, $id) {
                $user = Auth::user();
                $salesOrder = SalesOrder::where('business_id', $user->business_id)
                    ->with('items')
                    ->find($id);

                if (!$salesOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sales order not found'
                    ], 404);
                }

                if (!in_array($salesOrder->status, ['confirmed', 'shipped'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sales order must be confirmed before shipping'
                    ], 400);
                }

                // Generate shipment number if not provided
                $shipmentNumber = $request->shipment_number ?? 
                    'SH-' . $salesOrder->id . '-' . 
                    str_pad($salesOrder->shipments()->count() + 1, 3, '0', STR_PAD_LEFT);

                // Create shipment
                $shipment = SalesShipment::create([
                    'business_id' => $user->business_id,
                    'sales_order_id' => $salesOrder->id,
                    'shipment_number' => $shipmentNumber,
                    'shipped_date' => $request->shipped_date,
                    'expected_delivery_date' => $request->expected_delivery_date,
                    'status' => 'shipped',
                    'tracking_number' => $request->tracking_number,
                    'notes' => $request->notes,
                    'created_by' => $user->id,
                ]);

                $shipmentTotal = 0;

                // Process each item in the shipment
                foreach ($request->items as $itemData) {
                    $salesOrderItem = SalesOrderItem::find($itemData['sales_order_item_id']);
                    
                    if (!$salesOrderItem || $salesOrderItem->sales_order_id !== $salesOrder->id) {
                        throw new \Exception('Invalid sales order item');
                    }

                    $quantityShipped = $itemData['quantity_shipped'];
                    $unitPrice = $itemData['unit_price'];
                    $totalPrice = $quantityShipped * $unitPrice;
                    $shipmentTotal += $totalPrice;

                    // Check available quantity
                    $availableToShip = $salesOrderItem->quantity_ordered - $salesOrderItem->quantity_shipped;
                    if ($quantityShipped > $availableToShip) {
                        throw new \Exception("Cannot ship more than available quantity for item");
                    }

                    // Check stock availability
                    $product = Product::find($salesOrderItem->product_id);
                    if ($product->quantity < $quantityShipped) {
                        throw new \Exception("Insufficient stock for product: {$product->name}");
                    }

                    // Create shipment item
                    SalesShipmentItem::create([
                        'shipment_id' => $shipment->id,
                        'sales_order_item_id' => $salesOrderItem->id,
                        'quantity_shipped' => $quantityShipped,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'notes' => $itemData['notes'] ?? null,
                    ]);

                    // Update sales order item quantities
                    $salesOrderItem->increment('quantity_shipped', $quantityShipped);

                    // Update product stock
                    $quantityBefore = $product->quantity;
                    $product->decrement('quantity', $quantityShipped);

                    // Record inventory history
                    InventoryHistory::createRecord(
                        $user->business_id,
                        $salesOrderItem->product_id,
                        $user->id,
                        'stock-out',
                        $quantityShipped,
                        $quantityBefore,
                        "Sales Shipment: {$shipmentNumber}",
                        $shipment
                    );
                }

                // Update shipment total amount
                $shipment->update(['total_amount' => $shipmentTotal]);

                // Update sales order status
                $salesOrder->updateStatus();

                return response()->json([
                    'success' => true,
                    'message' => 'Sales order shipped successfully',
                    'data' => [
                        'shipment' => $shipment->load('items.salesOrderItem.product'),
                        'sales_order' => $salesOrder->fresh()->load(['customer', 'items.product', 'shipments'])
                    ]
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to ship sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shipments for a sales order.
     *
     * @param int $id The sales order ID
     * @return \Illuminate\Http\JsonResponse List of shipments
     */
    public function getShipments($id): JsonResponse
    {
        $user = Auth::user();
        $salesOrder = SalesOrder::where('business_id', $user->business_id)->find($id);

        if (!$salesOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Sales order not found'
            ], 404);
        }

        $shipments = $salesOrder->shipments()
            ->with(['items.salesOrderItem.product'])
            ->latest('shipped_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shipments
        ]);
    }

    /**
     * Add payment to sales order.
     *
     * @param Request $request The request containing payment data
     * @param int $id The sales order ID
     * @return \Illuminate\Http\JsonResponse Payment data or error message
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
            $salesOrder = SalesOrder::where('business_id', $user->business_id)->find($id);

            if (!$salesOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sales order not found'
                ], 404);
            }

            // Check if payment amount doesn't exceed due amount
            $dueAmount = $salesOrder->total_amount - $salesOrder->paid_amount;
            if ($request->amount > $dueAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds due amount'
                ], 400);
            }

            $payment = Payment::create([
                'business_id' => $user->business_id,
                'paymentable_type' => SalesOrder::class,
                'paymentable_id' => $salesOrder->id,
                'payment_method_id' => $request->payment_method_id,
                'amount' => $request->amount,
                'transaction_date' => $request->transaction_date,
                'details' => $request->details,
                'reference_number' => $request->reference_number,
                'status' => 'clear',
                'created_by' => $user->id,
            ]);

            // Update sales order paid amount
            $salesOrder->updatePaidAmount();

            // Update status if fully paid
            $salesOrder->updateStatus();

            return response()->json([
                'success' => true,
                'message' => 'Payment added successfully',
                'data' => [
                    'payment' => $payment->load('paymentMethod'),
                    'sales_order' => $salesOrder->fresh()
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
     * Cancel a sales order.
     *
     * @param int $id The sales order ID
     * @return \Illuminate\Http\JsonResponse Success message or error
     */
    public function cancel($id): JsonResponse
    {
        $user = Auth::user();
        $salesOrder = SalesOrder::where('business_id', $user->business_id)->find($id);

        if (!$salesOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Sales order not found'
            ], 404);
        }

        if ($salesOrder->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel completed sales order'
            ], 400);
        }

        if ($salesOrder->shipments()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel sales order with shipments'
            ], 400);
        }

        try {
            $salesOrder->update([
                'status' => 'cancelled',
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sales order cancelled successfully',
                'data' => $salesOrder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update sales order items for the specified sales order.
     *
     * Features:
     * - Updates existing items (quantity, unit price, notes)
     * - Adds new items to the sales order
     * - Removes items not included in the request
     * - Recalculates sales order totals automatically with tax
     * - Supports bulk operations within a transaction
     * - Validates all item data before making changes
     *
     * Business Rules:
     * - Only allows updates to orders with 'draft' or 'confirmed' status
     * - Can modify items with shipped quantities, but cannot reduce quantity below shipped amount
     * - Prevents deletion of items with shipped quantities
     * - Automatically recalculates order totals based on item changes
     * - Maintains audit trail with user tracking
     * - Allows adding new items even to partially shipped orders
     *
     * Security considerations:
     * - Only authenticated users can update their business sales orders
     * - Validates product existence and business ownership
     * - Prevents unauthorized access to other business data
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing items data
     * @param int $id The sales order ID
     * @return \Illuminate\Http\JsonResponse Updated sales order with items or error message
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
     *   "discount": 50.00,            // Optional: order discount
     *   "discount_type": "fixed",     // Optional: fixed or percentage
     *   "tax_rate": 10.00             // Optional: tax rate
     * }
     */
    public function updateItems(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:sales_order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:1000',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
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
                $salesOrder = SalesOrder::where('business_id', $user->business_id)
                    ->with(['items', 'customer'])
                    ->find($id);

                if (!$salesOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sales order not found'
                    ], 404);
                }

                // Check if sales order can be modified
                if (!in_array($salesOrder->status, ['draft', 'confirmed'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot update items for sales order with status: ' . $salesOrder->status
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

                // Check if any existing items are being updated to a quantity less than already shipped
                foreach ($request->items as $itemData) {
                    if (isset($itemData['id']) && $itemData['id']) {
                        $existingItem = $salesOrder->items()
                            ->where('id', $itemData['id'])
                            ->first();
                        
                        if ($existingItem && $existingItem->quantity_shipped > 0) {
                            if ($itemData['quantity_ordered'] < $existingItem->quantity_shipped) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "Cannot reduce quantity for item {$existingItem->product->name} below already shipped quantity ({$existingItem->quantity_shipped})"
                                ], 400);
                            }
                        }
                    }
                }

                // Identify items to delete (existing items not in the request)
                $itemsToDelete = $salesOrder->items()
                    ->whereNotIn('id', $updatingItemIds)
                    ->where('quantity_shipped', 0) // Only delete items with no shipped quantity
                    ->get();

                // Check if any items to delete have shipped quantities
                $itemsToDeleteWithShipped = $salesOrder->items()
                    ->whereNotIn('id', $updatingItemIds)
                    ->where('quantity_shipped', '>', 0)
                    ->count();

                if ($itemsToDeleteWithShipped > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot remove items that have already been partially or fully shipped'
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
                        $existingItem = SalesOrderItem::where('id', $itemData['id'])
                            ->where('sales_order_id', $salesOrder->id)
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
                        SalesOrderItem::create([
                            'sales_order_id' => $salesOrder->id,
                            'product_id' => $itemData['product_id'],
                            'quantity_ordered' => $itemData['quantity_ordered'],
                            'unit_price' => $itemData['unit_price'],
                            'total_price' => $totalPrice,
                            'notes' => $itemData['notes'] ?? null,
                            'created_by' => $user->id,
                        ]);
                    }
                }

                // Update sales order totals
                $discount = $request->get('discount', $salesOrder->discount);
                $discountType = $request->get('discount_type', $salesOrder->discount_type);
                $taxRate = $request->get('tax_rate', $salesOrder->tax_rate);

                // Calculate discount amount
                if ($discountType === 'percentage') {
                    $discountAmount = ($subTotal * $discount) / 100;
                } else {
                    $discountAmount = $discount;
                }

                $afterDiscount = $subTotal - $discountAmount;
                $taxAmount = ($afterDiscount * $taxRate) / 100;
                $totalAmount = $afterDiscount + $taxAmount;

                $salesOrder->update([
                    'sub_total' => $subTotal,
                    'discount' => $discountAmount,
                    'discount_type' => $discountType,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'updated_by' => $user->id,
                ]);

                // Reload sales order with fresh data
                $salesOrder = $salesOrder->fresh(['customer', 'items.product']);

                return response()->json([
                    'success' => true,
                    'message' => 'Sales order items updated successfully',
                    'data' => $salesOrder
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sales order items',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
