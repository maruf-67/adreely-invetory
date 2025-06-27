<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryHistory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class InventoryController extends BaseController
{

    public function __construct()
    {
        $this->middleware('role:admin|super-admin');
    }
    public function addStock(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string'
        ]);

        $product->increment('quantity', $request->quantity);

        InventoryHistory::create([
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => $request->quantity,
            'note' => $request->note
        ]);

        $this->checkLowStock($product);

        return response()->json([
            'success' => true,
            'message' => 'Stock added successfully',
            'data' => $product->refresh()
        ]);
    }

    public function removeStock(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:'.$product->quantity,
            'note' => 'nullable|string'
        ]);

        $product->decrement('quantity', $request->quantity);

        InventoryHistory::create([
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => $request->quantity,
            'note' => $request->note
        ]);

        $this->checkLowStock($product);

        return response()->json([
            'success' => true,
            'message' => 'Stock removed successfully',
            'data' => $product->refresh()
        ]);
    }

    public function stockHistory(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product->stockHistories()->latest()->get()
        ]);
    }

    public function lowStockAlerts()
    {
        $products = Product::where('quantity', '<=', \DB::raw('low_stock_threshold'))->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    private function checkLowStock(Product $product)
    {
        if ($product->quantity <= $product->low_stock_threshold) {
            // Implement your alert logic here
            // Example: Send notification, log event, or trigger email
        }
    }

}
