<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Ebook;

class CartController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | View Cart
    |--------------------------------------------------------------------------
    */

    public function viewCart()
    {
        try {

            $cart = Cart::with('items.ebook.images')
                ->firstOrCreate([
                    'user_id' => auth()->id(),
                    'status'  => 'ACTIVE'
                ]);

            $cart->recalculateTotals();

            // Correct subtotal calculation
            $subtotal = $cart->items->sum('total_price');

            $response = [
                'id'       => $cart->id,
                'user_id'  => $cart->user_id,
                'status'   => $cart->status,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'items'    => $cart->items->map(function ($item) {

                    return [
                        'id'       => $item->id,
                        'quantity' => $item->quantity,
                        'price'    => number_format($item->price, 2, '.', ''),
                        'total'    => number_format($item->total_price, 2, '.', ''),
                        'ebook'    => [
                            'id'    => $item->ebook->id,
                            'title' => $item->ebook->title,
                            'image' => $item->ebook->image
                        ]
                    ];
                })
            ];

            return response()->json($response, 200);

        } catch (\Throwable $th) {

            return response()->json([
                'error'   => 'Failed to fetch cart',
                'details' => $th->getMessage()
            ], 500);

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Add Item To Cart
    |--------------------------------------------------------------------------
    */
public function addItem(Request $request)
{
    try {

        $request->validate([
            'product_id' => 'required|exists:ebooks,id',
        ]);

        $product = Ebook::findOrFail($request->product_id);

        $cart = Cart::firstOrCreate([
            'user_id' => auth()->id(),
            'status'  => 'ACTIVE',
        ]);

        // Check if item already exists
        $existingItem = CartItem::where('cart_id', $cart->id)
            ->where('ebook_id', $product->id)
            ->first();

        // If already exists, return message (do not add again)
        if ($existingItem) {
            return response()->json([
                'message' => 'Product already in cart'
            ], 200);
        }

        // Create new cart item
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'ebook_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
            'total_price' => $product->price
        ]);

        $cart->recalculateTotals();
        $cart->load('items.ebook');

        return response()->json($cart, 200);

    } catch (\Throwable $th) {

        return response()->json([
            'error' => 'Failed to add item',
            'details' => $th->getMessage()
        ], 500);

    }
}

    /*
    |--------------------------------------------------------------------------
    | Update Cart Item Quantity
    |--------------------------------------------------------------------------
    */

    public function updateItem(Request $request, $itemId)
    {
        try {

            $request->validate([
                'quantity' => 'required|integer|min:0',
            ]);

            $item = CartItem::findOrFail($itemId);

            if ($request->quantity == 0) {

                $item->delete();

            } else {

                $item->quantity = $request->quantity;
                $item->total_price = $item->price * $item->quantity;
                $item->save();

            }

            $cart = $item->cart;
            $cart->recalculateTotals();
            $cart->load('items.ebook');

            return response()->json($cart, 200);

        } catch (\Throwable $th) {

            return response()->json([
                'error' => 'Failed to update item',
                'details' => $th->getMessage()
            ], 500);

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Item
    |--------------------------------------------------------------------------
    */

    public function removeItem($itemId)
    {
        try {

            $item = CartItem::findOrFail($itemId);
            $cart = $item->cart;

            $item->delete();

            $cart->recalculateTotals();
            $cart->load('items.ebook');

            return response()->json($cart, 200);

        } catch (\Throwable $th) {

            return response()->json([
                'error' => 'Failed to remove item',
                'details' => $th->getMessage()
            ], 500);

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Clear Cart
    |--------------------------------------------------------------------------
    */

    public function clearCart()
    {
        try {

            $cart = Cart::where('user_id', auth()->id())
                ->where('status', 'ACTIVE')
                ->first();

            if (!$cart) {

                return response()->json([
                    'message' => 'No active cart found'
                ], 404);

            }

            $cart->items()->delete();

            $cart->recalculateTotals();

            return response()->json([
                'message' => 'Cart cleared successfully'
            ], 200);

        } catch (\Throwable $th) {

            return response()->json([
                'error' => 'Failed to clear cart',
                'details' => $th->getMessage()
            ], 500);

        }
    }

}