<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Ebook;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    // ebooks => usually no shipping, tax maybe 0 (change if needed)
    private float $taxRate = 0.00;     // set 0.18 if you want GST

    private float $shipping = 0.00;

    // POST /api/checkout/validate
    // validate cart + address + price validation + totals
    public function validateCheckout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'cart_id' => 'required|exists:carts,id',
            'phone_number' => 'required|string|max:15',
            'address' => 'required|string|max:500',
            'pincode' => 'required|string|max:10',

            // optional anti-tamper check
            'expected_total' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $cart = Cart::with('items.ebook')
            ->where('id', $request->cart_id)
            ->where('user_id', $request->user_id)
            ->where('status', 'ACTIVE')
            ->first();

        if (! $cart) {
            return response()->json(['status' => false, 'message' => 'Invalid or inactive cart'], 400);
        }

        if ($cart->items->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'Cart is empty'], 400);
        }

        // ✅ always recalc from cart_items table
        $cart->recalculateTotals();
        $cart->refresh();

        $issues = [];

        // Price validation (recommended):
        // Compare cart_items.price vs current ebook price
        foreach ($cart->items as $item) {
            if (! $item->ebook) {
                $issues[] = [
                    'type' => 'EBOOK_MISSING',
                    'cart_item_id' => $item->id,
                    'message' => 'Ebook not found.',
                ];

                continue;
            }

            $currentPrice = (float) ($item->ebook->price ?? 0);
            $cartPrice = (float) ($item->price ?? 0);

            if (abs($currentPrice - $cartPrice) > 0.01) {
                $issues[] = [
                    'type' => 'PRICE_CHANGED',
                    'ebook_id' => $item->ebook->id,
                    'message' => 'Price changed for ebook.',
                    'old_price' => $cartPrice,
                    'new_price' => $currentPrice,
                ];
            }

            // Optional: verify total_price correctness
            // $expectedTotalPrice = round($cartPrice * (int)$item->quantity, 2);
            // $savedTotalPrice = round((float)($item->total_price ?? 0), 2);
            // if (abs($expectedTotalPrice - $savedTotalPrice) > 0.01) {
            //     $issues[] = [
            //         'type' => 'TOTAL_PRICE_MISMATCH',
            //         'cart_item_id' => $item->id,
            //         'message' => 'Cart item total_price mismatch.',
            //         'expected' => $expectedTotalPrice,
            //         'found' => $savedTotalPrice,
            //     ];
            // }
        }

        if (! empty($issues)) {
            return response()->json([
                'status' => false,
                'message' => 'Checkout validation failed',
                'issues' => $issues,
            ], 400);
        }

        // optional expected_total check (client total vs server subtotal)
        if ($request->filled('expected_total')) {
            $expected = (float) $request->expected_total;
            $serverSubtotal = (float) $cart->subtotal;

            if (abs($expected - $serverSubtotal) > 0.50) {
                return response()->json([
                    'status' => false,
                    'message' => 'Expected total does not match server subtotal.',
                    'expected_total' => round($expected, 2),
                    'server_subtotal' => round($serverSubtotal, 2),
                ], 400);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Checkout validated successfully',
            'cart' => [
                'cart_id' => $cart->id,
                'total_items' => (int) $cart->total_items,
                'subtotal' => (float) $cart->subtotal,
            ],
        ]);
    }

    // POST /api/checkout/summary
    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'cart_id' => 'required|exists:carts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $cart = Cart::with('items')
            ->where('id', $request->cart_id)
            ->where('user_id', $request->user_id)
            ->where('status', 'ACTIVE')
            ->first();

        if (! $cart) {
            return response()->json(['status' => false, 'message' => 'Invalid or inactive cart'], 400);
        }

        if ($cart->items->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'Cart is empty'], 400);
        }

        $cart->recalculateTotals();
        $cart->refresh();

        $subtotal = (float) $cart->subtotal;
        $tax = $subtotal * $this->taxRate;
        $shipping = $this->shipping;
        $grandTotal = $subtotal + $tax + $shipping;

        return response()->json([
            'status' => true,
            'data' => [
                'cart_id' => $cart->id,
                'item_count' => (int) $cart->total_items,
                'subtotal' => round($subtotal, 2),
                'tax_rate' => $this->taxRate,
                'tax' => round($tax, 2),
                'shipping' => round($shipping, 2),
                'grand_total' => round($grandTotal, 2),
            ],
        ]);
    }

    // POST /api/checkout/place-order
    // Only CASH mode active (COD)
    public function placeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'cart_id' => 'required|exists:carts,id',
            'phone_number' => 'required|string|max:15',
            'address' => 'required|string|max:500',
            'pincode' => 'required|string|max:10',

            // for now only cash
            'payment_method' => 'required|string|in:CASH,PAYTM,QR',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $cart = Cart::with('items.ebook')
            ->where('id', $request->cart_id)
            ->where('user_id', $request->user_id)
            ->where('status', 'ACTIVE')
            ->first();

        if (! $cart) {
            return response()->json(['status' => false, 'message' => 'Invalid or inactive cart'], 400);
        }

        if ($cart->items->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'Cart is empty'], 400);
        }

        // recalc totals server-side
        $cart->recalculateTotals();
        $cart->refresh();

        // price validation again (safe)
        foreach ($cart->items as $item) {
            if (! $item->ebook) {
                return response()->json(['status' => false, 'message' => 'One or more ebooks are missing'], 400);
            }
            $currentPrice = (float) ($item->ebook->price ?? 0);
            $cartPrice = (float) ($item->price ?? 0);

            if (abs($currentPrice - $cartPrice) > 0.01) {
                return response()->json([
                    'status' => false,
                    'message' => 'Price changed. Please refresh cart.',
                    'ebook_id' => $item->ebook->id,
                    'old_price' => $cartPrice,
                    'new_price' => $currentPrice,
                ], 400);
            }
        }

        $subtotal = (float) $cart->subtotal;
        $tax = $subtotal * $this->taxRate;
        $shipping = $this->shipping;
        $grandTotal = $subtotal + $tax + $shipping;

        // ✅ Create order (adjust fields to match your orders table)
        $order = Order::create([
            'user_id' => $request->user_id,
            'cart_id' => $request->cart_id,
            'phone_number' => $request->phone_number,
            'address' => $request->address,
            'pincode' => $request->pincode,
            'bill_amount' => round($grandTotal, 2),

            // if you have these columns:
            'order_no' => 'ORD-'.strtoupper(Str::random(10)),
            'status' => 'pending', // ✅ IMPORTANT: Initial status
            // 'payment_mode'   => 'CASH',
            // 'payment_status' => 'PENDING',
            // 'order_status'   => 'PLACED',
        ]);

        // update cart status
        // $cart->status = 'CHECKED_OUT';
        // $cart->save();

        return response()->json([
            'status' => true,
            'message' => 'Order placed successfully',
            'order' => $order,
            'amount_breakup' => [
                'subtotal' => round($subtotal, 2),
                'tax' => round($tax, 2),
                'shipping' => round($shipping, 2),
                'grand_total' => round($grandTotal, 2),
            ],
        ], 201);
    }

    public function checkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'cart_id' => 'required|exists:carts,id',
            'phone_number' => 'required|string|max:15',
            'address' => 'required|string',
            'pincode' => 'required|string|max:10',
            'bill_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        // 2️⃣ Get cart and verify ownership + status
        $cart = Cart::with('items.ebook')
            ->where('id', $request->cart_id)
            ->where('user_id', $request->user_id)
            ->where('status', 'ACTIVE')
            ->first();

        if (! $cart) {
            return response()->json([
                'message' => 'Invalid or inactive cart',
            ], 400);
        }

        if ($cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty',
            ], 400);
        }

        // 3️⃣ Store order
        $order = Order::create([
            'user_id' => $request->user_id,
            'cart_id' => $request->cart_id,
            'phone_number' => $request->phone_number,
            'address' => $request->address,
            'pincode' => $request->pincode,
            'bill_amount' => $request->bill_amount,
        ]);

        // (Optional) update cart status
        $cart->status = 'CHECKED_OUT';
        $cart->save();

        // 4️⃣ Response
        return response()->json([
            'message' => 'Order placed successfully',
            'order' => $order,
        ], 201);
    }

    public function orderhistory(Request $request, $userId)
    {
        $orders = Order::where('user_id', $userId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        $data = [];

        foreach ($orders as $order) {

            $items = \DB::table('cart_items')
                ->join('ebooks', 'cart_items.ebook_id', '=', 'ebooks.id')
                ->leftJoin('ebook_images', 'ebooks.id', '=', 'ebook_images.ebook_id')
                ->where('cart_items.cart_id', $order->cart_id)
                ->select(
                    'ebooks.id',
                    'ebooks.title',
                    'cart_items.price',
                    'cart_items.quantity',
                    'cart_items.total_price',
                    'ebook_images.image_path'
                )
                ->get();

            $ebooks = [];

            foreach ($items as $item) {

                $ebooks[] = [
                    'id' => $item->id,
                    'title' => $item->title,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'total' => $item->total_price ?: ($item->price * $item->quantity),
                    'image' => $item->image_path
                        ? url('laravel_project/public/'.$item->image_path)
                        : null,
                ];
            }

            $data[] = [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'bill_amount' => $order->bill_amount,
                'status' => $order->status,
                'created_at' => $order->created_at,
                'ebooks' => $ebooks,
            ];
        }

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    public function userDownloads($userId)
    {
        try {

            $downloads = DB::table('ebook_access')
                ->join('ebooks', 'ebook_access.ebook_id', '=', 'ebooks.id')
                ->leftJoin('ebook_images', 'ebooks.id', '=', 'ebook_images.ebook_id')
                ->where('ebook_access.user_id', $userId)
                ->where('ebook_access.is_active', 1)
                ->select(
                    'ebooks.id',
                    'ebooks.title',
                    'ebooks.description',
                    'ebook_images.image_path',
                    'ebook_access.created_at'
                )
                ->groupBy(
                    'ebooks.id',
                    'ebooks.title',
                    'ebooks.description',
                    'ebook_images.image_path',
                    'ebook_access.created_at'
                )
                ->orderByDesc('ebook_access.created_at')
                ->get();

            $data = [];

            foreach ($downloads as $item) {

                $data[] = [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,

                    'image' => $item->image_path
                        ? url('laravel_project/public/'.$item->image_path)
                        : null,

                    // direct secure download api
                    'file_url' => url('api/download-ebook/'.$item->id.'?user_id='.$userId),

                    'purchased_at' => $item->created_at,
                ];
            }

            return response()->json([
                'status' => true,
                'data' => $data,
            ]);

        } catch (\Throwable $th) {

            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD EBOOK
    |--------------------------------------------------------------------------
    */
    public function downloadEbook(Request $request, $id)
    {
        try {
            $userId = $request->user_id;

            if (! $userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'User ID missing',
                ], 400);
            }

            // Check purchase access
            $access = DB::table('ebook_access')
                ->where('user_id', $userId)
                ->where('ebook_id', $id)
                ->where('is_active', 1)
                ->first();

            if (! $access) {
                return response()->json([
                    'status' => false,
                    'message' => 'Access denied',
                ], 403);
            }

            $ebook = Ebook::find($id);

            if (! $ebook || ! $ebook->ebook_file) {
                return response()->json([
                    'status' => false,
                    'message' => 'File not found',
                ], 404);
            }

            $path = public_path($ebook->ebook_file);

            if (! File::exists($path)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Physical file missing',
                ], 404);
            }

            return response()->download(
                $path,
                basename($path),
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.basename($path).'"',
                ]
            );

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
