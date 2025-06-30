<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Product;
use App\Models\InventoryHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesOrderController extends Controller
{
    /**
     * Display a listing of sales orders for the business.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = SalesOrder::where('business_id', $user->business_id)
            ->with(['customer', 'items.product']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
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
     * Store a newly created sales order.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:users,id',
            'order_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'vat_gst_percent' => 'nullable|numeric|min:0|max:100',
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

                // Validate customer belongs to same business
                $customer = \App\Models\User::where('id', $request->customer_id)
                    ->where('business_id', $user->business_id)
                    ->whereIn('user_type', ['retailer', 'dealer', 'wholesaler', 'guest'])
                    ->first();

                if (!$customer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid customer selected'
                    ], 400);
                }

                // Check stock availability
                foreach ($request->items as $item) {
                    $product = Product::where('id', $item['product_id'])
                        ->where('business_id', $user->business_id)
                        ->first();

                    if (!$product) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid product selected'
                        ], 400);
                    }

                    if ($product->quantity < $item['quantity']) {
                        return response()->json([
                            'success' => false,
                            'message' => "Insufficient stock for product: {$product->name}. Available: {$product->quantity}"
                        ], 400);
                    }
                }

                // Calculate totals
                $subTotal = 0;
                foreach ($request->items as $item) {
                    $subTotal += $item['quantity'] * $item['unit_price'];
                }

                $vatGstPercent = $request->vat_gst_percent ?? 0;
                $discount = $request->discount ?? 0;
                $vatGstAmount = ($subTotal * $vatGstPercent) / 100;
                $totalAmount = $subTotal + $vatGstAmount - $discount;

                // Generate invoice number
                $lastInvoice = SalesOrder::where('business_id', $user->business_id)
                    ->latest('id')
                    ->first();
                $invoiceNumber = 'INV-' . $user->business_id . '-' . str_pad(($lastInvoice->id ?? 0) + 1, 6, '0', STR_PAD_LEFT);

                // Create sales order
                $salesOrder = SalesOrder::create([
                    'business_id' => $user->business_id,
                    'customer_id' => $request->customer_id,
                    'invoice_number' => $invoiceNumber,
                    'order_date' => $request->order_date,
                    'status' => 'pending',
                    'sub_total' => $subTotal,
                    'vat_gst_percent' => $vatGstPercent,
                    'discount' => $discount,
                    'total_amount' => $totalAmount,
                    'paid_amount' => 0,
                    'created_by' => $user->id,
                ]);

                // Create sales order items and update stock
                foreach ($request->items as $item) {
                    SalesOrderItem::create([
                        'sales_order_id' => $salesOrder->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                    ]);

                    // Update product stock
                    $product = Product::find($item['product_id']);
                    $product->decrement('quantity', $item['quantity']);

                    // Record inventory history
                    InventoryHistory::create([
                        'business_id' => $user->business_id,
                        'product_id' => $item['product_id'],
                        'user_id' => $user->id,
                        'type' => 'stock-out',
                        'quantity_change' => -$item['quantity'],
                        'reason' => 'Sales Order Fulfilled',
                        'reference_id' => $salesOrder->id,
                        'reference_type' => 'App\Models\SalesOrder',
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
     * Display the specified sales order.
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        $salesOrder = SalesOrder::where('business_id', $user->business_id)
            ->with(['customer', 'items.product', 'creator'])
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

        if ($salesOrder->status === 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update delivered sales order'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'in:pending,processing,shipped,delivered,cancelled',
            'vat_gst_percent' => 'nullable|numeric|min:0|max:100',
            'discount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $salesOrder->update($request->only([
            'status', 'vat_gst_percent', 'discount'
        ]));

        // Recalculate total if VAT/GST or discount changed
        if ($request->has('vat_gst_percent') || $request->has('discount')) {
            $vatGstAmount = ($salesOrder->sub_total * $salesOrder->vat_gst_percent) / 100;
            $totalAmount = $salesOrder->sub_total + $vatGstAmount - $salesOrder->discount;
            $salesOrder->update(['total_amount' => $totalAmount]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sales order updated successfully',
            'data' => $salesOrder->load(['customer', 'items.product'])
        ]);
    }

    /**
     * Cancel a sales order and restore stock.
     */
    public function cancel($id): JsonResponse
    {
        try {
            return DB::transaction(function () use ($id) {
                $user = Auth::user();
                $salesOrder = SalesOrder::where('business_id', $user->business_id)->find($id);

                if (!$salesOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sales order not found'
                    ], 404);
                }

                if ($salesOrder->status === 'delivered') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot cancel delivered sales order'
                    ], 400);
                }

                // Restore stock for each item
                foreach ($salesOrder->items as $item) {
                    $product = Product::find($item->product_id);
                    $product->increment('quantity', $item->quantity);

                    // Record inventory history
                    InventoryHistory::create([
                        'business_id' => $user->business_id,
                        'product_id' => $item->product_id,
                        'user_id' => $user->id,
                        'type' => 'adjustment',
                        'quantity_change' => $item->quantity,
                        'reason' => 'Sales Order Cancelled - Stock Restored',
                        'reference_id' => $salesOrder->id,
                        'reference_type' => 'App\Models\SalesOrder',
                    ]);
                }

                $salesOrder->update(['status' => 'cancelled']);

                return response()->json([
                    'success' => true,
                    'message' => 'Sales order cancelled successfully and stock restored',
                    'data' => $salesOrder
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = SalesOrder::where('business_id', $user->business_id);

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('order_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('order_date', '<=', $request->to_date);
        }

        $totalSales = $query->sum('total_amount');
        $totalOrders = $query->count();
        $averageOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

        $statusBreakdown = $query->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('status')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'average_order_value' => round($averageOrderValue, 2),
                'status_breakdown' => $statusBreakdown
            ]
        ]);
    }
}
