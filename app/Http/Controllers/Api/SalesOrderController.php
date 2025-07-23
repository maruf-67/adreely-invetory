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
use App\Models\PaymentMethod;
use App\Models\UserBalance;
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

                // Verify customer belongs to the same business and has valid customer role (excludes admin, staff, supplier)
                $customer = User::where('id', $request->customer_id)
                    ->where('business_id', $user->business_id)
                    ->whereNotIn('user_type', ['admin', 'staff', 'supplier'])
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
                if ($discountType == 'percentage') {
                    $discountAmount = ($subTotal * $discount) / 100;
                } else {
                    $discountAmount = $discount;
                }

                $afterDiscount = $subTotal - $discountAmount;
                $taxRate = $request->get('tax_rate', 0);
                $taxAmount = ($afterDiscount * $taxRate) / 100;
                $totalAmount = $afterDiscount + $taxAmount;

                // Create sales order with 'pending' status instead of 'pending'
                $salesOrder = SalesOrder::create([
                    'business_id' => $user->business_id,
                    'customer_id' => $request->customer_id,
                    'order_date' => $request->order_date,
                    'expected_delivery_date' => $request->expected_delivery_date,
                    'status' => 'completed',
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

                // Validate stock availability for all items before creating order
                foreach ($request->items as $itemData) {
                    $product = Product::where('id', $itemData['product_id'])
                        ->where('business_id', $user->business_id)
                        ->first();
                    
                    if (!$product) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid product selected'
                        ], 400);
                    }
                    
                    if ($product->quantity < $itemData['quantity_ordered']) {
                        return response()->json([
                            'success' => false,
                            'message' => "Insufficient stock for product: {$product->name}. Available: {$product->quantity}, Requested: {$itemData['quantity_ordered']}"
                        ], 400);
                    }
                }

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
     * - Only allows updates to orders in pending or confirmed status
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

        if (!in_array($salesOrder->status, ['pending', 'partial'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update sales order that is not pending or partial'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:pending,partial,completed,cancelled'
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
                'status' => $request->status,
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
     * - Changes status from pending to confirmed
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

        if ($salesOrder->status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Sales order is not in pending status'
            ], 400);
        }

        try {
            return DB::transaction(function () use ($salesOrder, $user) {
                // Check stock availability and deduct stock
                foreach ($salesOrder->items as $item) {
                    $product = Product::find($item->product_id);
                    if ($product->quantity < $item->quantity_ordered) {
                        throw new \Exception("Insufficient stock for product: {$product->name}. Available: {$product->quantity}, Required: {$item->quantity_ordered}");
                    }
                    
                    // Deduct stock from product
                    $quantityBefore = $product->quantity;
                    $product->decrement('quantity', $item->quantity_ordered);
                    
                    // Record inventory history
                    InventoryHistory::createRecord(
                        $user->business_id,
                        $item->product_id,
                        $user->id,
                        'stock-out',
                        $item->quantity_ordered,
                        $quantityBefore,
                        "Sales Order Confirmed: {$salesOrder->order_number}",
                        $salesOrder
                    );
                }

                $salesOrder->update([
                    'status' => 'confirmed',
                    'updated_by' => $user->id,
                ]);

                // Generate invoice number
                $salesOrder->generateInvoiceNumber();

                return response()->json([
                    'success' => true,
                    'message' => 'Sales order confirmed successfully and stock deducted',
                    'data' => $salesOrder->fresh()
                ]);
            });
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

                if (!in_array($salesOrder->status, ['partial', 'confirmed'])) {
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

                    if (!$salesOrderItem || $salesOrderItem->sales_order_id != $salesOrder->id) {
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
     * Features:
     * - Supports instant and cheque payments
     * - Updates customer balance appropriately
     * - Handles payment status and order updates
     * - Manages overpayment scenarios
     *
     * Security considerations:
     * - Only authenticated users can add payments to their business sales orders
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing payment data
     * @param int $id The sales order ID
     * @return \Illuminate\Http\JsonResponse Payment data or error message
     */
    public function addPayment(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:instant,cheque',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'details' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'cheque_number' => 'required_if:type,cheque|string',
            'bank_name' => 'required_if:type,cheque|string',
            'cheque_date' => 'required_if:type,cheque|date',
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
                $salesOrder = SalesOrder::where('business_id', $user->business_id)->find($id);

                if (!$salesOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sales order not found'
                    ], 404);
                }

                // Verify payment method belongs to the business
                $paymentMethod = PaymentMethod::where('id', $request->payment_method_id)
                    ->where('business_id', $user->business_id)
                    ->first();

                if (!$paymentMethod) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid payment method'
                    ], 400);
                }

                // Determine payment status based on type
                $status = $request->type == 'instant' ? 'clear' : 'pending';

                // Prepare payment details
                $paymentDetails = $request->details;
                if ($request->type == 'cheque') {
                    $chequeDetails = "Cheque No: {$request->cheque_number}, Bank: {$request->bank_name}, Date: {$request->cheque_date}";
                    $paymentDetails = $paymentDetails ? $paymentDetails . " | " . $chequeDetails : $chequeDetails;
                }

                $payment = Payment::create([
                    'business_id' => $user->business_id,
                    'paymentable_type' => SalesOrder::class,
                    'paymentable_id' => $salesOrder->id,
                    'payment_method_id' => $request->payment_method_id,
                    'amount' => $request->amount,
                    'transaction_date' => $request->transaction_date,
                    'details' => $paymentDetails,
                    'reference_number' => $request->reference_number,
                    'status' => $status,
                    'created_by' => $user->id,
                ]);

                // Handle payment effects based on status
                if ($status == 'clear') {
                    $overpaymentInfo = $this->handleClearedPaymentEffect($salesOrder, $payment);
                    $responseMessage = 'Payment added and cleared successfully';
                    if ($overpaymentInfo['overpayment_amount'] > 0) {
                        $responseMessage .= ". Overpayment of " . number_format($overpaymentInfo['overpayment_amount'], 2) . " added to customer balance.";
                    }
                } else {
                    $responseMessage = 'Payment added successfully (pending cheque clearance)';
                }

                // Update sales order status
                $salesOrder->updateStatus();

                return response()->json([
                    'success' => true,
                    'message' => $responseMessage,
                    'data' => [
                        'payment' => $payment->load('paymentMethod'),
                        'sales_order' => $salesOrder->fresh(),
                        'overpayment_info' => $overpaymentInfo ?? null
                    ]
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment status for a sales order payment.
     *
     * Features:
     * - Updates payment status (pending/clear/hold/rejected)
     * - Manages customer balance effects when status changes
     * - Updates sales order totals and status
     * - Handles overpayment scenarios appropriately
     *
     * Security considerations:
     * - Only authenticated users can update their business payment statuses
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing status update data
     * @param int $id The sales order ID
     * @param int $paymentId The payment ID
     * @return \Illuminate\Http\JsonResponse Updated payment data or error message
     */
    public function updatePaymentStatus(Request $request, $id, $paymentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,clear,hold,rejected',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request, $id, $paymentId) {
                $user = Auth::user();
                $salesOrder = SalesOrder::where('business_id', $user->business_id)->find($id);

                if (!$salesOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sales order not found'
                    ], 404);
                }

                $payment = Payment::where('id', $paymentId)
                    ->where('paymentable_type', SalesOrder::class)
                    ->where('paymentable_id', $salesOrder->id)
                    ->where('business_id', $user->business_id)
                    ->first();

                if (!$payment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment not found'
                    ], 404);
                }

                $oldStatus = $payment->status;
                $newStatus = $request->status;

                // Update payment status
                $payment->update([
                    'status' => $newStatus,
                ]);

                $responseMessage = "Payment status updated to {$newStatus}";
                $balanceEffect = null;

                // Handle balance effects based on status change
                if ($oldStatus != 'clear' && $newStatus == 'clear') {
                    // Payment became cleared
                    $balanceEffect = $this->handleClearedPaymentEffect($salesOrder, $payment);
                    if ($balanceEffect['overpayment_amount'] > 0) {
                        $responseMessage .= ". Overpayment of " . number_format($balanceEffect['overpayment_amount'], 2) . " added to customer balance.";
                    }
                } elseif ($oldStatus == 'clear' && $newStatus != 'clear') {
                    // Payment is no longer cleared
                    $balanceEffect = $this->handlePaymentReversalEffect($salesOrder, $payment);
                    if ($balanceEffect['balance_adjustment'] > 0) {
                        $responseMessage .= ". Customer balance adjusted by " . number_format($balanceEffect['balance_adjustment'], 2) . " due to payment reversal.";
                    }
                }

                // Update sales order status
                $salesOrder->updateStatus();

                return response()->json([
                    'success' => true,
                    'message' => $responseMessage,
                    'data' => [
                        'payment' => $payment->fresh(),
                        'sales_order' => $salesOrder->fresh(),
                        'balance_effect' => $balanceEffect
                    ]
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment status',
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

        if ($salesOrder->status == 'completed') {
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
     * - Only allows updates to orders with 'pending' or 'confirmed' status
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
                if (!in_array($salesOrder->status, ['pending', 'partial'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot update sales order that is not pending or partial'
                    ], 400);
                }

                // Verify all products belong to the business and validate stock for confirmed orders
                $productIds = collect($request->items)->pluck('product_id');
                $validProducts = Product::where('business_id', $user->business_id)
                    ->whereIn('id', $productIds)
                    ->get()
                    ->keyBy('id');

                if ($productIds->diff($validProducts->keys())->count() > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'One or more products do not belong to your business'
                    ], 400);
                }

                // For confirmed orders, validate stock availability for quantity increases
                if ($salesOrder->status == 'confirmed') {
                    foreach ($request->items as $itemData) {
                        $product = $validProducts[$itemData['product_id']];
                        $existingItem = null;
                        
                        if (isset($itemData['id']) && $itemData['id']) {
                            $existingItem = $salesOrder->items()->where('id', $itemData['id'])->first();
                        }
                        
                        $currentQuantity = $existingItem ? $existingItem->quantity_ordered : 0;
                        $newQuantity = $itemData['quantity_ordered'];
                        $quantityIncrease = $newQuantity - $currentQuantity;
                        
                        // Only check stock if quantity is increasing
                        if ($quantityIncrease > 0) {
                            if ($product->quantity < $quantityIncrease) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "Insufficient stock for product: {$product->name}. Available: {$product->quantity}, Additional needed: {$quantityIncrease}"
                                ], 400);
                            }
                        }
                    }
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

                // Delete items that are no longer needed and restore stock for confirmed orders
                foreach ($itemsToDelete as $item) {
                    // For confirmed orders, restore stock when deleting items
                    if ($salesOrder->status == 'confirmed') {
                        $product = Product::find($item->product_id);
                        $quantityBefore = $product->quantity;
                        $product->increment('quantity', $item->quantity_ordered);

                        // Record inventory history
                        InventoryHistory::createRecord(
                            $user->business_id,
                            $item->product_id,
                            $user->id,
                            'stock-in',
                            $item->quantity_ordered,
                            $quantityBefore,
                            "Sales Order Item Removed: {$salesOrder->order_number}",
                            $salesOrder
                        );
                    }
                    
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
                            $oldQuantity = $existingItem->quantity_ordered;
                            $newQuantity = $itemData['quantity_ordered'];
                            $quantityDifference = $newQuantity - $oldQuantity;

                            // For confirmed orders, adjust stock based on quantity changes
                            if ($salesOrder->status == 'confirmed' && $quantityDifference != 0) {
                                $product = Product::find($itemData['product_id']);
                                $quantityBefore = $product->quantity;

                                if ($quantityDifference > 0) {
                                    // Quantity increased - deduct more stock
                                    $product->decrement('quantity', $quantityDifference);
                                    $historyReason = "Sales Order Item Updated - Quantity Increased: {$salesOrder->order_number}";
                                    $historyType = 'stock-out';
                                } else {
                                    // Quantity decreased - return stock
                                    $product->increment('quantity', abs($quantityDifference));
                                    $historyReason = "Sales Order Item Updated - Quantity Decreased: {$salesOrder->order_number}";
                                    $historyType = 'stock-in';
                                }

                                // Record inventory history
                                InventoryHistory::createRecord(
                                    $user->business_id,
                                    $itemData['product_id'],
                                    $user->id,
                                    $historyType,
                                    abs($quantityDifference),
                                    $quantityBefore,
                                    $historyReason,
                                    $salesOrder
                                );
                            }

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
                        $newItem = SalesOrderItem::create([
                            'sales_order_id' => $salesOrder->id,
                            'product_id' => $itemData['product_id'],
                            'quantity_ordered' => $itemData['quantity_ordered'],
                            'unit_price' => $itemData['unit_price'],
                            'total_price' => $totalPrice,
                            'notes' => $itemData['notes'] ?? null,
                            'created_by' => $user->id,
                        ]);

                        // For confirmed orders, deduct stock for new items
                        if ($salesOrder->status == 'confirmed') {
                            $product = Product::find($itemData['product_id']);
                            $quantityBefore = $product->quantity;
                            $product->decrement('quantity', $itemData['quantity_ordered']);

                            // Record inventory history
                            InventoryHistory::createRecord(
                                $user->business_id,
                                $itemData['product_id'],
                                $user->id,
                                'stock-out',
                                $itemData['quantity_ordered'],
                                $quantityBefore,
                                "Sales Order New Item Added: {$salesOrder->order_number}",
                                $salesOrder
                            );
                        }
                    }
                }

                // Update sales order totals
                $discount = $request->get('discount', $salesOrder->discount);
                $discountType = $request->get('discount_type', $salesOrder->discount_type);
                $taxRate = $request->get('tax_rate', $salesOrder->tax_rate);

                // Calculate discount amount
                if ($discountType == 'percentage') {
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

    /**
     * Handle cleared payment effects on sales order balance and customer balance.
     * This function is called whenever a payment becomes cleared (either new payment or status update).
     * For sales orders, payments come FROM customers TO the business.
     * 
     * @param SalesOrder $salesOrder The sales order
     * @param Payment $payment The payment that was cleared
     * @return array Returns overpayment information
     */
    private function handleClearedPaymentEffect(SalesOrder $salesOrder, Payment $payment): array
    {
        // Recalculate sales order paid amount (only cleared payments)
        $salesOrder->updatePaidAmount();

        // Calculate current totals
        $clearedPayments = $salesOrder->payments()->where('status', 'clear')->sum('amount');
        $totalAmount = $salesOrder->total_amount;
        $overpaymentAmount = max(0, $clearedPayments - $totalAmount);

        // Update sales order extra_amount field (if it exists in the model)
        if (in_array('extra_amount', $salesOrder->getFillable())) {
            $salesOrder->update(['extra_amount' => $overpaymentAmount]);
        }

        // Handle customer balance for overpayment
        // In sales, overpayment creates a CREDIT for the customer (business owes them)
        if ($overpaymentAmount > 0) {
            UserBalance::updateOrCreate(
                [
                    'business_id' => $salesOrder->business_id,
                    'user_id' => $salesOrder->customer_id,
                ],
                []
            )->increment('current_balance', $overpaymentAmount);

            // Log the balance change
            UserBalance::logBalanceChange(
                $salesOrder->business_id,
                $salesOrder->customer_id,
                $overpaymentAmount,
                'credit_adjustment',
                "Overpayment from sales order #{$salesOrder->order_number}",
                $salesOrder->id,
                SalesOrder::class
            );
        }

        return [
            'overpayment_amount' => $overpaymentAmount,
            'total_cleared_payments' => $clearedPayments,
            'order_total' => $totalAmount,
            'balance_effect' => $overpaymentAmount > 0 ? 'customer_credit_increased' : 'no_balance_change'
        ];
    }

    /**
     * Handle payment reversal effects when payment status changes from clear to non-clear.
     * This function reverses balance effects when a payment is no longer cleared.
     * For sales orders, this reduces customer credit when payment is reversed.
     * 
     * @param SalesOrder $salesOrder The sales order
     * @param Payment $payment The payment that is no longer cleared
     * @return array Returns reversal information
     */
    private function handlePaymentReversalEffect(SalesOrder $salesOrder, Payment $payment): array
    {
        // Calculate what overpayment was before including this payment
        $clearedPaymentsWithThisPayment = $salesOrder->payments()
            ->where('status', 'clear')
            ->orWhere('id', $payment->id)
            ->sum('amount');

        $clearedPaymentsWithoutThisPayment = $salesOrder->payments()
            ->where('status', 'clear')
            ->where('id', '!=', $payment->id)
            ->sum('amount');

        $totalAmount = $salesOrder->total_amount;
        $previousOverpay = max(0, $clearedPaymentsWithThisPayment - $totalAmount);
        $newOverpay = max(0, $clearedPaymentsWithoutThisPayment - $totalAmount);
        $overpayReduction = $previousOverpay - $newOverpay;

        // Update sales order paid amount and extra amount
        $salesOrder->updatePaidAmount();
        if (in_array('extra_amount', $salesOrder->getFillable())) {
            $salesOrder->update(['extra_amount' => $newOverpay]);
        }

        // Handle customer balance reversal
        // In sales, reducing overpayment reduces customer credit (business owes them less)
        if ($overpayReduction > 0) {
            UserBalance::updateOrCreate(
                [
                    'business_id' => $salesOrder->business_id,
                    'user_id' => $salesOrder->customer_id,
                ],
                []
            )->decrement('current_balance', $overpayReduction);

            // Log the balance change
            UserBalance::logBalanceChange(
                $salesOrder->business_id,
                $salesOrder->customer_id,
                -$overpayReduction,
                'debit_adjustment',
                "Payment reversal from sales order #{$salesOrder->order_number}",
                $salesOrder->id,
                SalesOrder::class
            );
        }

        return [
            'overpay_reduction' => $overpayReduction,
            'new_overpay_amount' => $newOverpay,
            'balance_adjustment' => $overpayReduction,
            'balance_effect' => $overpayReduction > 0 ? 'customer_credit_decreased' : 'no_balance_change'
        ];
    }
}
