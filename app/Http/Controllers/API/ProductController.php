<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin|super-admin');
    }
    public function index()
    {
        return response()->json(['success' => true, 'data' => Product::all()]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
        ]);
        $product = Product::create($validated);
        return response()->json(['success' => true, 'data' => $product], 201);
    }
    public function show($id)
    {
        $product = Product::findOrFail($id);
        return response()->json(['success' => true, 'data' => $product]);
    }
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
        ]);
        $product->update($validated);
        return response()->json(['success' => true, 'data' => $product]);
    }
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json(['success' => true, 'message' => 'Product deleted successfully']);
    }
}
