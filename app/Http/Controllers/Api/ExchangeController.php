<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exchange;
use App\Models\ExchangeItem;
use App\Models\ExchangePayment;
use App\Models\InventoryHistory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExchangeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Exchange::where('business_id', $user->business_id)
            ->with(['user', 'items.product', 'payments.paymentMethod']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $exchanges = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $exchanges
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shop_name' => 'required|string|max:255',
            'shop_contact' => 'nullable|string|max:255',
            'type' => 'required|in:source,sell',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
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

                $totalAmount = 0;
                foreach ($request->items as $item) {
                    $totalAmount += $item['quantity'] * $item['unit_price'];
                }

                $exchange = Exchange::create([
                    'business_id' => $user->business_id,
                    'user_id' => $user->id,
                    'shop_name' => $request->shop_name,
                    'shop_contact' => $request->shop_contact,
                    'type' => $request->type,
                    'status' => 'pending',
                    'total_amount' => $totalAmount,
                    'notes' => $request->notes,
                ]);

                foreach ($request->items as $itemData) {
                    ExchangeItem::create([
                        'exchange_id' => $exchange->id,
                        'product_id' => $itemData['product_id'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                    ]);

                    $product = Product::find($itemData['product_id']);
                    $quantityBefore = $product->quantity;

                    if ($request->type === 'source') {
                        $product->increment('quantity', $itemData['quantity']);
                        $historyType = 'stock-in';
                    } else {
                        $product->decrement('quantity', $itemData['quantity']);
                        $historyType = 'stock-out';
                    }

                    InventoryHistory::createRecord(
                        $user->business_id,
                        $itemData['product_id'],
                        $user->id,
                        $historyType,
                        $itemData['quantity'],
                        $quantityBefore,
                        "Exchange #{$exchange->id}",
                        $exchange
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Exchange created successfully',
                    'data' => $exchange->load(['user', 'items.product'])
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create exchange',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $user = Auth::user();
        $exchange = Exchange::where('business_id', $user->business_id)
            ->with(['user', 'items.product', 'payments.paymentMethod'])
            ->find($id);

        if (!$exchange) {
            return response()->json([
                'success' => false,
                'message' => 'Exchange not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $exchange
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $exchange = Exchange::where('business_id', $user->business_id)->find($id);

        if (!$exchange) {
            return response()->json([
                'success' => false,
                'message' => 'Exchange not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'shop_name' => 'sometimes|required|string|max:255',
            'shop_contact' => 'nullable|string|max:255',
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
            $exchange->update($request->only(['shop_name', 'shop_contact', 'notes']));

            return response()->json([
                'success' => true,
                'message' => 'Exchange updated successfully',
                'data' => $exchange->fresh()->load(['user', 'items.product'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update exchange',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $exchange = Exchange::where('business_id', $user->business_id)->find($id);

        if (!$exchange) {
            return response()->json([
                'success' => false,
                'message' => 'Exchange not found'
            ], 404);
        }

        if ($exchange->payments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an exchange with payments. Please cancel it instead.'
            ], 400);
        }

        try {
            DB::transaction(function () use ($exchange, $user) {
                foreach ($exchange->items as $item) {
                    $product = Product::find($item->product_id);
                    $quantityBefore = $product->quantity;

                    if ($exchange->type === 'source') {
                        $product->decrement('quantity', $item->quantity);
                        $historyType = 'stock-out';
                    } else {
                        $product->increment('quantity', $item->quantity);
                        $historyType = 'stock-in';
                    }

                    InventoryHistory::createRecord(
                        $user->business_id,
                        $item->product_id,
                        $user->id,
                        $historyType,
                        $item->quantity,
                        $quantityBefore,
                        "Reversal for Exchange #{$exchange->id}",
                        $exchange
                    );
                }

                $exchange->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Exchange deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete exchange',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addPayment(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
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
                $exchange = Exchange::where('business_id', $user->business_id)->find($id);

                if (!$exchange) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Exchange not found'
                    ], 404);
                }

                $payment = ExchangePayment::create([
                    'exchange_id' => $exchange->id,
                    'payment_method_id' => $request->payment_method_id,
                    'amount' => $request->amount,
                    'payment_date' => $request->payment_date,
                    'notes' => $request->notes,
                ]);

                $paidAmount = $exchange->payments()->sum('amount');
                $exchange->update(['paid_amount' => $paidAmount]);

                if ($paidAmount >= $exchange->total_amount) {
                    $status = $paidAmount > $exchange->total_amount ? 'overpaid' : 'paid';
                } else {
                    $status = 'partially_paid';
                }
                $exchange->update(['status' => $status]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment added successfully',
                    'data' => [
                        'payment' => $payment->load('paymentMethod'),
                        'exchange' => $exchange->fresh()->load(['user', 'items.product', 'payments.paymentMethod'])
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

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $exchange = Exchange::where('business_id', $user->business_id)->find($id);

        if (!$exchange) {
            return response()->json([
                'success' => false,
                'message' => 'Exchange not found'
            ], 404);
        }

        try {
            $exchange->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Exchange status updated successfully',
                'data' => $exchange->fresh()->load(['user', 'items.product', 'payments.paymentMethod'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update exchange status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
