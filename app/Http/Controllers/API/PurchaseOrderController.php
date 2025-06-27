<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller as BaseController;

class PurchaseOrderController extends BaseController
{
    public function __construct()
    {
        $this->middleware('role:admin|super-admin');
    }
    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {
            // Validate request
            $validated = $request->validate([
                'supplier_id' => 'nullable|exists:suppliers,id',
                'new_supplier' => 'nullable|array',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'payment_method' => 'required|in:cash,credit,bank_transfer',
                'payment_date' => 'required|date',
                'notes' => 'nullable|string'
            ]);

            // Handle new supplier creation
            if ($request->has('new_supplier')) {
                $supplier = Supplier::create($request->new_supplier);
                $supplierId = $supplier->id;
            } else {
                $supplierId = $validated['supplier_id'];
            }

            // Create purchase order
            $po = PurchaseOrder::create([
                'supplier_id' => $supplierId,
                'total_amount' => 0, // Will be calculated
                'payment_method' => $validated['payment_method'],
                'order_date' => now(),
                'status' => 'pending'
            ]);

            // Add items and calculate total
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $poItem = $po->items()->create($item);
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            // Update total amount
            $po->update(['total_amount' => $totalAmount]);

            // Handle payment
            if ($validated['payment_method'] !== 'credit') {
                Payment::create([
                    'purchase_order_id' => $po->id,
                    'amount' => $totalAmount,
                    'method' => $validated['payment_method'],
                    'payment_date' => $validated['payment_date'],
                    'notes' => $validated['notes'] ?? null
                ]);
            } else {
                // Update supplier credit balance
                $supplier = Supplier::find($supplierId);
                $supplier->increment('balance_credit', $totalAmount);
            }

            return response()->json([
                'success' => true,
                'data' => $po->load('items', 'supplier', 'payments')
            ], 201);
        });
    }

    public function deliverOrder(PurchaseOrder $purchaseOrder)
    {
        return DB::transaction(function () use ($purchaseOrder) {
            if ($purchaseOrder->status === 'clear') {
                return response()->json([
                    'message' => 'Order already delivered'
                ], 400);
            }

            // Update stock
            foreach ($purchaseOrder->items as $item) {
                app('App\Http\Controllers\API\InventoryController')
                    ->addStock(new Request([
                        'quantity' => $item->quantity,
                        'note' => 'Purchase Order #' . $purchaseOrder->id
                    ]), $item->product);
            }

            $purchaseOrder->update([
                'status' => 'clear',
                'delivery_date' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $purchaseOrder
            ]);
        });
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => PurchaseOrder::with('items.product', 'supplier', 'payments')->get()
        ]);
    }
}
