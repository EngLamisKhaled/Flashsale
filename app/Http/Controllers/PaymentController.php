<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Hold;
use App\Models\Product;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function webhook(Request $request)
    {
        // 1) Validate incoming data
        $data = $request->validate([
            'order_id'        => 'required|integer|exists:orders,id',
            'status'          => 'required|string|in:success,failure',
            'idempotency_key' => 'required|string',
        ]);

        return DB::transaction(function () use ($data, $request) {

            // 2) Idempotency check: if this key was processed before, ignore
            $existing = PaymentEvent::where('idempotency_key', $data['idempotency_key'])->first();

            if ($existing) {
                // Already processed – just return same status
                return response()->json([
                    'message' => 'Already processed',
                    'status'  => $existing->status,
                ]);
            }

            // 3) Lock the order row (and related product) for update
            $order = Order::where('id', $data['order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $hold = Hold::where('id', $order->hold_id)
                ->lockForUpdate()
                ->first();

            $product = Product::where('id', $order->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 4) Apply business logic based on payment status
            if ($data['status'] === 'success') {

                if ($order->status !== 'paid') {
                    // mark order paid
                    $order->status = 'paid';
                    $order->save();

                    // move qty from "reserved" to "sold"
                    $product->stock_sold += $order->qty;
                    $product->save();

                    // finalize hold
                    if ($hold && $hold->status !== 'completed') {
                        $hold->status = 'completed';
                        $hold->save();
                    }
                }
            } else { // failure

                if ($order->status !== 'canceled') {
                    $order->status = 'canceled';
                    $order->save();
                }

                // free the reserved stock
                if ($hold && in_array($hold->status, ['active', 'used'])) {
                    $hold->status = 'canceled';
                    $hold->save();
                }
            }

            // 5) Save payment event to enforce idempotency next time
            PaymentEvent::create([
                'order_id'        => $order->id,
                'idempotency_key' => $data['idempotency_key'],
                'status'          => $data['status'],
                'raw_payload'     => json_encode($request->all()),
            ]);

            return response()->json([
                'message' => 'Payment handled',
                'order_status' => $order->status,
            ]);
        });
    }
}
