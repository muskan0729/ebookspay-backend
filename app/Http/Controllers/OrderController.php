<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Ebook;
use App\Models\CartItem;

use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function viewOrder($id)
    {
        // $userId = auth()->id();
        // return [$id,$userId];
        try {
            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $cart = Cart::where('id', $order->cart_id)
                ->where('status', 'CHECKED_OUT')
                ->first();

            if (!$cart) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart not found'
                ], 404);
            }

            $items = CartItem::with('ebook')
                ->where('cart_id', $cart->id)
                ->get();

            // $item=$items->id
            return response()->json([
                'order' => $order,
                'cart'  => $cart,
                'items' => $items,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th], 404);
        }
    }
    public function cancelOrder(Request $request, $id)
    {
        // return $id;
        try{
             $order = Order::find($id)::where('status','Placed')->first();
             if (!$order) {
                return response()->json([
                'status'=> false,
                'message'=> 'Invalid Ordeer'
                ], 404);
             }
             $order->status='Pending';
             $order->save();
             return response()->json([
                'status'=> true,
                'message'=> 'Order Cancelled '
             ]);
        } catch (\Throwable $th) {
            throw $th;
        }
    }



}
