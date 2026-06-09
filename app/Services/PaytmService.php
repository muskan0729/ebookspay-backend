<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use paytm\paytmchecksum\PaytmChecksum;

class PaytmService
{
    private $mid;
    private $merchantKey;
    private $websiteName;

    public function __construct()
    {
        $this->mid = env('PAYTM_MID');
        $this->merchantKey = env('PAYTM_MERCHANT_KEY');
        $this->websiteName = env('PAYTM_WEBSITE', 'DEFAULT');
    }

    /* ======================================================
        1. INITIATE TRANSACTION
    ====================================================== */
    public function initiateTransaction(Order $order,?string $callbackUrl = null)
    {
        try {

            $orderId = $order->order_no;
            $amount = number_format($order->bill_amount, 2, '.', '');

            $body = [
                "requestType" => "Payment",
                "mid" => $this->mid,
                "websiteName" => $this->websiteName,
                "orderId" => $orderId,
                
               // "callbackUrl" => "https://ebookspay.co.in/payment-result?orderId=" . $orderId,
                "callbackUrl" => $callbackUrl
    ?: "https://ebookspay.co.in/payment-result?orderId=".$orderId,

                "txnAmount" => [
                    "value" => $amount,
                    "currency" => "INR",
                ],

                "userInfo" => [
                    "custId" => (string) $order->user_id,
                ],
            ];

            $checksum = PaytmChecksum::generateSignature(
                json_encode($body, JSON_UNESCAPED_SLASHES),
                $this->merchantKey
            );

            $paytmParams = [
                "body" => $body,
                "head" => [
                    "signature" => $checksum
                ]
            ];
            Log::info('PAYTM INITIATE REQUEST', [
                'orderId' => $orderId,
                'mid' => $this->mid,
                'amount' => $amount
            ]);
            $url = "https://secure.paytmpayments.com/theia/api/v1/initiateTransaction"
                . "?mid={$this->mid}&orderId={$orderId}";

            $response = $this->curlPost($url, $paytmParams);


            Log::info('PAYTM INITIATE RESPONSE', [
                'url' => $url,
                'response' => $response
            ]);

            if (!$response) {
                return ['status' => false, 'message' => 'No response from Paytm'];
            }

            $txnToken = $response['body']['txnToken'] ?? null;

            if (!$txnToken) {
                return [
                    'status' => false,
                    'message' => 'TxnToken missing',
                    'response' => $response
                ];
            }

            return [
                'status' => true,
                'orderId' => $orderId,
                'txnToken' => $txnToken,
                'amount' => $amount,
                'mid' => $this->mid,
                'redirectUrl' => "https://ebookspay.co.in/payment-result?orderId=$orderId",
               
                'raw' => $response
            ];

        } catch (\Exception $e) {
            Log::error("Paytm Initiate Error: " . $e->getMessage());

            return [
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* ======================================================
        2. PAYTM STATUS API (NEW)
    ====================================================== */
    public function checkStatus($orderId)
    {
        try {

            $body = [
                "mid" => $this->mid,
                "orderId" => $orderId,
            ];

            $checksum = PaytmChecksum::generateSignature(
                json_encode($body, JSON_UNESCAPED_SLASHES),
                $this->merchantKey
            );

            $url = "https://secure.paytmpayments.com/v3/order/status";

            $payload = [
                "body" => $body,
                "head" => [
                    "signature" => $checksum
                ]
            ];

            $response = $this->curlPost($url, $payload);

            return [
                'status' => true,
                'data' => $response
            ];

        } catch (\Exception $e) {

            return [
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* ======================================================
        3. WEBHOOK VERIFY + PROCESS (VERY IMPORTANT)
    ====================================================== */
    public function handleWebhook(array $data)
    {
        $orderId = $data['ORDERID'] ?? null;
        $status = $data['STATUS'] ?? null;

        if (!$orderId || !$status) {
            Log::channel('paytm')->warning('INVALID WEBHOOK PAYLOAD', $data);

            return ['status' => false, 'message' => 'Invalid payload'];
        }

        $order = Order::where('order_no', $orderId)->first();

        if (!$order) {
            Log::channel('paytm')->warning('ORDER NOT FOUND', ['order_id' => $orderId]);

            return ['status' => false, 'message' => 'Order not found'];
        }

        // duplicate protection
        if (!empty($order->txn_id) && $order->txn_id === ($data['TXNID'] ?? null)) {
            return ['status' => true, 'message' => 'Already processed'];
        }

        DB::beginTransaction();

        try {

            $order->update([
                'status' => $status === 'TXN_SUCCESS' ? 'completed' : 'failed',
                'payment_status' => $status,
                'txn_id' => $data['TXNID'] ?? null,
                'bank_txn_id' => $data['BANKTXNID'] ?? null,
                'payment_mode' => $data['PAYMENTMODE'] ?? null,
                'txn_date' => $data['TXNDATE'] ?? null,
                'status_response' => json_encode($data),
            ]);

            if ($status === 'TXN_SUCCESS') {
                $this->grantEbookAccess($order);

                Log::channel('paytm')->info('PAYMENT SUCCESS', [
                    'order_id' => $orderId,
                    'txn_id' => $data['TXNID'] ?? null,
                ]);
            }

            DB::commit();

            // ðŸ”¥ IMPORTANT: send UAT callback AFTER commit
            $this->sendUatCallback($order, $data);

            return ['status' => true];

        } catch (\Exception $e) {

            DB::rollBack();

            Log::channel('paytm')->error('WEBHOOK FAILED', [
                'error' => $e->getMessage(),
                'payload' => $data
            ]);

            return ['status' => false];
        }
    }



    /* ======================================================
        4. CURL HELPER
    ====================================================== */
    private function curlPost($url, $data)
    {
        $ch = curl_init();

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Content-Length: " . strlen($payload)
            ],
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            Log::error("CURL ERROR: " . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        return json_decode($response, true);
    }

    private function sendUatCallback(Order $order, array $paytmData)
    {
        $url = 'https://uatfintech.spay.live/api/callback/update/prod/ebookpaytmcallbkp';

        $payload = [
            'orderId' => $order->order_no,
            'status' => $paytmData['STATUS'] ?? null,
            'txnId' => $paytmData['TXNID'] ?? null,
            'bankTxnId' => $paytmData['BANKTXNID'] ?? null,
            'amount' => $paytmData['TXNAMOUNT'] ?? $order->bill_amount,
            'paymentMode' => $paytmData['PAYMENTMODE'] ?? null,
            'txnDate' => $paytmData['TXNDATE'] ?? null,
            'respMsg' => $paytmData['RESPMSG'] ?? null,
        ];

        Log::channel('paytm')->info('UAT CALLBACK INIT', [
            'url' => $url,
            'payload' => $payload
        ]);

        try {

            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {

                Log::channel('paytm')->error('UAT CALLBACK CURL ERROR', [
                    'error' => curl_error($ch),
                    'payload' => $payload
                ]);

            } else {

                Log::channel('paytm')->info('UAT CALLBACK SENT', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
            }

            curl_close($ch);

        } catch (\Exception $e) {

            Log::channel('paytm')->error('UAT CALLBACK EXCEPTION', [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function syncOrderStatus(Order $order)
    {

    if ($order->status !== 'pending') {
        return true;
    }
        $result = $this->checkStatus($order->order_no);

        if (!$result['status']) {
            return false;
        }

        $response = $result['data'];

        $status = $response['body']['resultInfo']['resultStatus'] ?? null;

        if ($status === 'TXN_SUCCESS') {

            $order->update([
                'status' => 'completed',
                'payment_status' => 'TXN_SUCCESS',
                'txn_id' => $response['body']['txnId'] ?? null,
                'bank_txn_id' => $response['body']['bankTxnId'] ?? null,
                'payment_mode' => $response['body']['paymentMode'] ?? null,
                'txn_date' => $response['body']['txnDate'] ?? null,
                'status_response' => json_encode($response)
            ]);

            $this->grantEbookAccess($order);

        } elseif ($status === 'TXN_FAILURE') {

            $order->update([
                'status' => 'failed',
                'payment_status' => 'TXN_FAILURE',
                'status_response' => json_encode($response)
            ]);
        }

        return true;
    }

    /* ======================================================
        5. EBOOK ACCESS
    ====================================================== */
    private function grantEbookAccess($order)
    {
        $cart = \App\Models\Cart::with('items')->find($order->cart_id);

        if (!$cart)
            return;

        foreach ($cart->items as $item) {

            $exists = DB::table('ebook_access')
                ->where('user_id', $order->user_id)
                ->where('ebook_id', $item->ebook_id)
                ->exists();

            if (!$exists) {
                DB::table('ebook_access')->insert([
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'ebook_id' => $item->ebook_id,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}