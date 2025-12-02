<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Hold;

use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show($id)
    {
        $product = Product::findOrFail($id);

        $activeHoldsQty = Hold::where('product_id', $id)
            ->whereIn('status', ['active', 'used'])
            ->where('expires_at', '>', now())
            ->sum('qty');


        $available = $product->stock_total - $product->stock_sold - $activeHoldsQty;


        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'available_stock' => $available,
        ]);
    }
}
