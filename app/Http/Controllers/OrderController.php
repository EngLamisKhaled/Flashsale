<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hold;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // 1) Validate request
        $data = $request->validate([
            'hold_id' => 'required|exists:holds,id',
        ]);

        // 2) Get the hold
        $hold = Hold::findOrFail($data['hold_id']);

        // 3) Check if hold is still valid
        if ($hold->status !== 'active') {
            return response()->json(['error' => 'Hold is not active'], 400);
        }

        if ($hold->expires_at <= now()) {
            return response()->json(['error' => 'Hold expired'], 400);
        }

        // 4) Get product
        $product = Product::findOrFail($hold->product_id);

        // 5) Calculate total price
        $totalPrice = $product->price * $hold->qty;

        // 6) Create order
        $order = Order::create([
            'product_id'  => $product->id,
            'hold_id'     => $hold->id,
            'qty'         => $hold->qty,
            'total_price' => $totalPrice,
            'status'      => 'pending',
        ]);

        // 7) Mark hold as used
        $hold->status = 'used';
        $hold->save();

        return response()->json([
            'order_id' => $order->id,
            'status'   => $order->status,
        ], 201);
    }
}
