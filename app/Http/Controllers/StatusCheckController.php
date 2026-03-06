<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StatusCheckController extends Controller
{
    protected $merchant_id;
    protected $username;
    protected $password;
    protected $client_id;
    protected $client_secret;
    protected $secret;
    protected $secretKey;

    public function __construct()
    {
        $this->merchant_id   = "353405";
        $this->username      = "zY4KPwTjP4";
        $this->password      = "dqQE4f8z";
        $this->client_id     = "454969";
        $this->client_secret = "4fbb61f1f5a95a242b14f4e44218dcc5";
        $this->secret        = "SRsB9xGfMFsgkzKt"; // Different secret for status
        $this->secretKey     = md5($this->username . "~:~" . $this->password); // Different key generation
    }

    /* =========================
       ENCRYPT
    ========================= */
    private function encrypt($data, $key)
    {
        $iv = bin2hex(openssl_random_pseudo_bytes(8));
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        return $iv . base64_encode($encrypted);
    }

    /* =========================
       DECRYPT
    ========================= */
    private function decrypt($response, $key)
    {
        $iv   = substr($response, 0, 16);
        $data = substr($response, 16);
        return openssl_decrypt(
            base64_decode($data),
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    /* =========================
       CHECKSUM
    ========================= */
    private function checksum($data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $value) {
            $str .= $value;
        }
        return hash('sha256', $str . date('Y-m-d'));
    }

    /* =========================
       GET ACCESS TOKEN
    ========================= */
    private function getAccessToken()
    {
        $tokenRequest = [
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'merchant_id'   => $this->merchant_id,
            'grant_type'    => 'client_credentials'
        ];

        $payload = [
            'merchant_id' => $this->merchant_id,
            'encdata'     => $this->encrypt(json_encode($tokenRequest), $this->secretKey),
            'checksum'    => $this->checksum($tokenRequest)
        ];

        $ch = curl_init("https://kraken.airpay.co.in/airpay/pay/v4/api/oauth2");

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            Log::error('Status: CURL Error in getAccessToken: ' . curl_error($ch));
            return ['error' => curl_error($ch)];
        }

        curl_close($ch);

        $tokenArr = json_decode($response, true);

        if (!isset($tokenArr['response'])) {
            Log::error('Status: Invalid token response');
            return ['error' => 'Invalid token response'];
        }

        $decrypted = $this->decrypt($tokenArr['response'], $this->secretKey);
        $tokenData = json_decode($decrypted, true);

        if (empty($tokenData['data']['access_token'])) {
            Log::error('Status: Token generation failed');
            return ['error' => 'Token generation failed'];
        }

        return $tokenData['data']['access_token'];
    }

    /* =========================
       CHECK STATUS - UPDATE ORDER WITH FULL RESPONSE
    ========================= */
    public function checkStatus(Request $request)
    {
        try {
            // Validate required fields
            $request->validate([
                'ap_transactionid' => 'required',
                'order_no' => 'required'
            ]);

            Log::info('Status: Checking payment status', [
                'order_no' => $request->order_no,
                'ap_transactionid' => $request->ap_transactionid
            ]);

            // ============== FIND PENDING ORDER WITH MATCHING CREDENTIALS ==============
            $order = Order::where('order_no', $request->order_no)
                          ->where('transaction_id', $request->ap_transactionid)
                          ->where('status', 'pending')  // Only check pending orders
                          ->first();

            if (!$order) {
                Log::warning('Status: No pending order found', [
                    'order_no' => $request->order_no,
                    'transaction_id' => $request->ap_transactionid
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'No pending order found'
                ], 404);
            }

            // ============== GET ACCESS TOKEN ==============
            $accessToken = $this->getAccessToken();

            if (!is_string($accessToken)) {
                return response()->json($accessToken, 500);
            }

            // ============== PREPARE STATUS CHECK ==============
            $privatekey = hash(
                'sha256',
                $this->secret . '@' . $this->username . ':|:' . $this->password
            );

            $statusData = [
                'merchant_id'      => $this->merchant_id,
                'ap_transactionid' => $request->ap_transactionid,
                'orderid'          => $request->order_no
            ];

            $payload = [
                'merchant_id' => $this->merchant_id,
                'encdata'     => $this->encrypt(json_encode($statusData), $this->secretKey),
                'checksum'    => $this->checksum($statusData),
                'privatekey'  => $privatekey
            ];

            // ============== CALL AIRPAY API ==============
            $ch = curl_init(
                "https://kraken.airpay.co.in/airpay/pay/v4/api/verify/?token=" . $accessToken
            );

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($payload),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                $curlError = curl_error($ch);
                curl_close($ch);
                Log::error('Status: CURL Error: ' . $curlError);
                return response()->json(['error' => $curlError], 500);
            }

            curl_close($ch);

            // ============== DECRYPT RESPONSE ==============
            $statusArr = json_decode($response, true);

            if (!isset($statusArr['response'])) {
                Log::error('Status: Invalid response structure');
                return response()->json([
                    'error' => 'Invalid status response'
                ], 400);
            }

            $decrypted = $this->decrypt($statusArr['response'], $this->secretKey);
            $airpayResponse = json_decode($decrypted, true);
            
            Log::info('Status: Airpay response', $airpayResponse);

            // ============== HANDLE 108 (NORMAL BEFORE PAYMENT) ==============
            if (isset($airpayResponse['status_code']) && $airpayResponse['status_code'] === '108') {
                return response()->json([
                    'status' => 'success',
                    'response' => $airpayResponse,
                    'order_status' => 'pending',
                    'message' => 'Payment not initiated'
                ]);
            }

            // ============== UPDATE ORDER STATUS WITH FULL RESPONSE ==============
            $paymentData = $airpayResponse['data'] ?? [];
            $oldStatus = $order->status;
            $statusChanged = false;

            // Check transaction payment status
            if (isset($paymentData['transaction_payment_status'])) {
                $txnStatus = strtoupper($paymentData['transaction_payment_status']);
                if ($txnStatus === 'SUCCESS') {
                    $order->status = 'completed';
                    //$order->payment_completed_at = now();
                    $statusChanged = true;
                } elseif ($txnStatus === 'FAILED') {
                    $order->status = 'failed';
                    $statusChanged = true;
                }
            }
            
            // Check transaction_status field
            if (isset($paymentData['transaction_status'])) {
                if ($paymentData['transaction_status'] == 200) {
                    $order->status = 'completed';
                    //$order->payment_completed_at = now();
                    $statusChanged = true;
                } elseif (in_array($paymentData['transaction_status'], [400, 501])) {
                    $order->status = 'failed';
                    $statusChanged = true;
                }
            }

            // ✅ STORE FULL AIRPAY RESPONSE ONLY WHEN STATUS CHANGES
            if ($statusChanged) {
                $order->payment_response = json_encode($airpayResponse);
                $order->save();

                Log::info('✅ Status: Order status updated with full response', [
                    'order_no' => $order->order_no,
                    'old_status' => $oldStatus,
                    'new_status' => $order->status
                ]);
            }

            return response()->json([
                'status' => 'success',
                'response' => $airpayResponse,
                'order_status' => $order->status
            ]);

        } catch (\Exception $e) {
            Log::error('Status: Exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check status: ' . $e->getMessage()
            ], 500);
        }
    }
}