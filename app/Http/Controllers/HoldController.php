<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Hold;
use Illuminate\Http\Request;

class HoldController extends Controller
{
    public function store(Request $request)
    {
        // 1) Validate the incoming data
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
        ]);

        // 2) Get the product
        $product = Product::find($data['product_id']);

        // 3) Calculate active holds (reserved quantities)
        $activeHoldsQty = Hold::where('product_id', $product->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->sum('qty');

        $available = $product->stock_total - $product->stock_sold - $activeHoldsQty;

        if ($available < $data['qty']) {
            return response()->json(['error' => 'Not enough stock'], 400);
        }

        // 4) Create the hold
        $hold = Hold::create([
            'product_id' => $product->id,
            'qty' => $data['qty'],
            'status' => 'active',
            'expires_at' => now()->addMinutes(2),
        ]);

        return response()->json([
            'hold_id' => $hold->id,
            'expires_at' => $hold->expires_at
        ]);
    }
}
