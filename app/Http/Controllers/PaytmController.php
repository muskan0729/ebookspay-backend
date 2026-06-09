<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\PaytmService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaytmController extends Controller
{
    private $paytmService;

    public function __construct(PaytmService $paytmService)
    {
        $this->paytmService = $paytmService;
    }
    public function createToken(Request $request)
    {
        $request->validate([
            'orderId' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'callback_url' => 'nullable|url',
        ]);

        try {

            $order = Order::firstOrCreate(
                ['order_no' => $request->orderId],
                [
                    'bill_amount' => $request->amount,
                    'user_id' => 1,
                    'cart_id' => 99,
                    'status' => 'pending',
                    'phone_number' => '',
                    'address' => '',
                    'pincode' => '',
                ]
            );

            Log::channel('paytm')->info('TOKEN ORDER CREATED', [
                'db_id' => $order->id,
                'order_no' => $order->order_no
            ]);

            $result = $this->paytmService->initiateTransaction($order,$request->callback_url);
            
            $order->update([
                'txn_token' => $result['txnToken']
            ]);
            if (!$result['status']) {
                return response()->json($result, 500);
            }

            return response()->json([
                'status' => true,
                'orderId' => $result['orderId'],
                'txnToken' => $result['txnToken'],
                'mid' => $result['mid'],
                'amount' => $result['amount'],
                "payment_url" => url('/paytm/pay/' . $result['orderId'])

            ]);

        } catch (\Exception $e) {

            Log::channel('paytm')->error('CREATE TOKEN ERROR', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }



    public function checkoutPage($orderId)
    {
        $order = Order::where('order_no', $orderId)->firstOrFail();

        return view('paytm.checkout', compact('order'));
    }


    /* =========================
        INITIATE PAYMENT
    ========================= */
    public function initiatePayment(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'cart_id' => 'required',
            'amount' => 'required|numeric|min:1',
            'phone_number' => 'required',
            'address' => 'required',
        ]);
        Log::channel('paytm')->info('INITIATE PAYMENT START', [
            'user_id' => $request->user_id,
            'amount' => $request->amount,
            'cart_id' => $request->cart_id,
        ]);

        DB::beginTransaction();



        $orderId = "ORD_" . time() . rand(1000, 9999);

        $order = Order::create([
            'order_no' => $orderId,
            'user_id' => $request->user_id,
            'cart_id' => $request->cart_id,

            'phone_number' => $request->phone_number ?? 'NA',
            'address' => $request->address ?? 'NA',
            'pincode' => $request->pincode ?? 'NA',

            'bill_amount' => $request->amount,
            'status' => 'pending'
        ]);



        $result = $this->paytmService->initiateTransaction($order);

        Log::channel('paytm')->info('PAYTM INIT RESPONSE', [
            'order_id' => $orderId,
            'response' => $result
        ]);

        if (!$result['status']) {
            DB::rollBack();

            return response()->json($result, 500);
        }
        DB::commit();


        $order->update([
            'txn_token' => $result['txnToken'],
            'payment_response' => json_encode($result['raw'])
        ]);

        return response()->json($result);
    }

    /* =========================
        STATUS API (NEW CLEAN)
    ========================= */


    public function checkStatus(Request $request)
    {
        $order = Order::where('order_no', $request->orderId)->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->status === 'pending') {


            if ($order->status === 'pending') {

                $this->paytmService->syncOrderStatus($order);

                $order->refresh();
            }

        }

        return response()->json([
            'status' => true,
            'order_status' => $order->status,
            'payment_status' => $order->payment_status,
        ]);
    }
    // public function checkStatus(Request $request)
    // {
    //     $order = Order::where('order_no', $request->orderId)->first();

    //     if (!$order) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Order not found'
    //         ], 404);
    //     }


    //     return response()->json([
    //         'status' => true,
    //         'order_status' => $order->status,
    //         'payment_status' => $order->payment_status,
    //         'txn_id' => $order->txn_id,
    //         'bank_txn_id' => $order->bank_txn_id,
    //         'payment_mode' => $order->payment_mode,
    //     ]);
    // }



    public function webhook(Request $request)
    {
        Log::channel('paytm')->info('WEBHOOK HIT', [
            'payload' => $request->all(),
        ]);

        $result = $this->paytmService->handleWebhook($request->all());

        return response()->json($result);
    }
}