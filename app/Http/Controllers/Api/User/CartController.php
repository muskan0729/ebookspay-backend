<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Ebook;

class CartController extends Controller
{
    // Get current user's cart
    // public function viewCart()
//    //     try {
    //         $cart = Cart::with('items.ebook')
    //             ->firstOrCreate([
    //                 'user_id' => auth()->id(),
    //                 'status'  => 'ACTIVE'
    //             ]);

    //         return response()->json($cart, 200);

    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             'error' => 'Failed to fetch cart',
    //             'details' => $th->getMessage()
    //         ], 500);
    //     }
    // }
    
    public function viewCart()
{
    try {
        $cart = Cart::with('items.ebook.images') // eager load images
            ->firstOrCreate([
                'user_id' => auth()->id(),
                'status'  => 'ACTIVE'
            ]);

        // Make sure totals are up-to-date
        $cart->recalculateTotals();

        // Calculate subtotal (sum of all items' total_price)
        $subtotal = $cart->items->sum(function ($item) {
    return $item->price;
});

// Format subtotal as decimal with 2 places
$subtotal = number_format($subtotal, 2, '.', ''); // e.g., 123.45

// Transform the response
$response = [
    'id'       => $cart->id,
    'user_id'  => $cart->user_id,
    'status'   => $cart->status,
    'subtotal' => $subtotal,
    'items'    => $cart->items->map(function ($item) {
        return [
            'id'       => $item->id,
            'quantity' => $item->quantity,
            'price'    => number_format($item->price, 2, '.', ''),
            'total'    => number_format($item->total_price, 2, '.', ''),
            'ebook'    => [
                'id'    => $item->ebook->id,
                'title' => $item->ebook->title,
                'image' => $item->ebook->image, // uses accessor
            ]
        ];
    
            }),
        ];

        return response()->json($response, 200);

    } catch (\Throwable $th) {
        return response()->json([
            'error'   => 'Failed to fetch cart',
            'details' => $th->getMessage()
        ], 500);
    }
}


    // Add item to cart
    // Add item to cart
public function addItem(Request $request)
{
    try {
        $request->validate([
            'product_id' => 'required|exists:ebooks,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $product = Ebook::findOrFail($request->product_id);

        $cart = Cart::firstOrCreate([
            'user_id' => auth()->id(),
            'status'  => 'ACTIVE',
        ]);

        $item = CartItem::firstOrNew([
            'cart_id'  => $cart->id,
            'ebook_id' => $product->id
        ]);

        $item->quantity = ($item->quantity ?? 0) + $request->quantity;
        $item->price = $product->price; // backend price only
        
        // ✅ FIX: Calculate and save total_price
        $item->total_price = $item->price * $item->quantity;
        
        $item->save();

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
    // Update quantity
   // Update quantity
public function updateItem(Request $request, $itemId)
{
    try {
        $request->validate([
            'quantity' => 'required|integer|min:0',
        ], [
            'quantity.required' => 'Quantity is required',
            'quantity.integer'  => 'Quantity must be a number',
            'quantity.min'      => 'Quantity must be at least 0',
        ]);

        $item = CartItem::findOrFail($itemId);
        $item->quantity = $request->quantity;

        if ($item->quantity == 0) {
            $item->delete();
        } else {
            // ✅ FIX: Update total_price when quantity changes
            $item->total_price = $item->price * $item->quantity;
            $item->save();
        }

        $item->cart->recalculateTotals();
        $item->cart->load('items.ebook');

        return response()->json($item->cart, 200);

    } catch (\Throwable $th) {
        return response()->json([
            'error' => 'Failed to update item',
            'details' => $th->getMessage()
        ], 500);
    }
}
    // Remove item
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

    // Clear cart
    public function clearCart()
    {
        try {
            $userId = auth()->id();

            $cart = Cart::where('user_id', $userId)
                        ->where('status', 'ACTIVE')
                        ->first();

            if (!$cart) {
                return response()->json([
                    'message' => 'No active cart found'
                ], 404);
            }

            $cart->items()->delete(); // safer than deleting cart
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
