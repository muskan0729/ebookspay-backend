<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QRCodeController extends Controller
{
    protected $merchant_id   = "353405";
    protected $username      = "zY4KPwTjP4";
    protected $password      = "dqQE4f8z";
    protected $client_id     = "454969";
    protected $client_secret = "4fbb61f1f5a95a242b14f4e44218dcc5";
    protected $secretKey     = "67d5c956c204bb6719bff713904d5bd7";
    protected $secret        = "4fbb61f1f5a95a242b14f4e44218dcc5";

    public function __construct(Request $request)
    {
        $individualIdentifier = $request->input('individualIdentifier');
        if ($individualIdentifier) {
            $data = json_decode($individualIdentifier, true);
            $this->merchant_id   = $data['merchant_id'] ?? $this->merchant_id;
            $this->username      = $data['username'] ?? $this->username;
            $this->password      = $data['password'] ?? $this->password;
            $this->client_id     = $data['client_id'] ?? $this->client_id;
            $this->client_secret = $data['client_secret'] ?? $this->client_secret;
            $this->secretKey     = $data['secretKey'] ?? $this->secretKey;
            $this->secret        = $data['secret'] ?? $this->secret;
        }
    }

    // =========================
    // GET ACCESS TOKEN
    // =========================
    private function getAccessToken()
    {
        try {
            $data = [
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'merchant_id'   => $this->merchant_id,
                'grant_type'    => 'client_credentials'
            ];

            $encdata  = $this->encrypt(json_encode($data), $this->secretKey);
            $checksum = $this->checksum($data);

            $payload = [
                'merchant_id' => $this->merchant_id,
                'encdata'     => $encdata,
                'checksum'    => $checksum
            ];

            Log::info('QR: Requesting access token from Airpay');

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://kraken.airpay.co.in/airpay/pay/v4/api/oauth2/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $result = curl_exec($curl);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                Log::error('QR: CURL Error in getAccessToken: ' . $curlError);
                return null;
            }

            if (!$result) {
                Log::error('QR: Empty response from Airpay token API');
                return null;
            }

            $response = json_decode($result);
            if (!$response || !isset($response->response)) {
                Log::error('QR: Invalid response from Airpay token API');
                return null;
            }

            $access_token_data = $this->decrypt($response->response, $this->secretKey);
            $resp = json_decode($access_token_data, true);

            if (isset($resp['data']['access_token'])) {
                Log::info('QR: Access token obtained successfully');
                return $resp['data']['access_token'];
            }

            Log::error('QR: Failed to get access token');
            return null;

        } catch (\Exception $e) {
            Log::error('QR: Exception in getAccessToken: ' . $e->getMessage());
            return null;
        }
    }

    // =========================
    // GENERATE QR - STORE ONLY TRANSACTION ID
    // =========================
    public function generateQR(Request $request)
    {
        try {
            // Validate required fields
            $postedData = $request->all();
            $required = ['orderid', 'amount', 'buyer_email', 'buyer_phone'];
            foreach ($required as $field) {
                if (empty($postedData[$field])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Missing required field: $field"
                    ], 400);
                }
            }

            Log::info('QR: Generating QR for order: ' . $postedData['orderid']);

            // Get access token
            $access_token = $this->getAccessToken();
            if (!$access_token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to get access token from Airpay'
                ], 500);
            }

            // Prepare data for Airpay
            $data = [
                'orderid'       => $postedData['orderid'],
                'amount'        => $postedData['amount'],
                'buyer_email'   => $postedData['buyer_email'],
                'buyer_phone'   => $postedData['buyer_phone'],
                'call_type'     => 'upiqr',
                'mer_dom'       => base64_encode("https://omishajewels.com"),
                'customer_consent' => 'Y'
            ];

            ksort($data);

            // Generate required fields
            $privatekey = hash('sha256', $this->secret . '@' . $this->username . ':|:' . $this->password);
            $encdata    = $this->encrypt(json_encode($data), $this->secretKey);
            $checksum   = $this->checksum($data);

            $payload = [
                'merchant_id' => $this->merchant_id,
                'encdata' => $encdata,
                'checksum' => $checksum,
                'privatekey' => $privatekey
            ];

            $url = "https://kraken.airpay.co.in/airpay/pay/v4/api/generateorder/?token=" . $access_token;

            Log::info('QR: Sending request to Airpay', ['orderid' => $postedData['orderid']]);

            // CURL request
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $result = curl_exec($curl);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                Log::error('QR: CURL Error: ' . $curlError);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Connection error'
                ], 500);
            }

            if (!$result) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Empty response from Airpay'
                ], 500);
            }

            $response = json_decode($result, true);
            
            if (!isset($response['response'])) {
                Log::error('QR: Invalid response structure');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid QR response from Airpay'
                ], 400);
            }

            // Decrypt the response
            // Decrypt the response
$decrypted = $this->decrypt($response['response'], $this->secretKey);
$decodedResponse = json_decode($decrypted, true);

Log::info('QR: Decoded response structure:', $decodedResponse);

// ============== FIXED: EXTRACT TRANSACTION ID FROM NESTED DATA ==============
$order = Order::where('order_no', $postedData['orderid'])->first();

if ($order) {
    // ✅ Correct way: ap_transactionid is inside ['data'] array
    $apTransactionId = $decodedResponse['data']['ap_transactionid'] ?? null;
    
    if ($apTransactionId) {
        // Store ONLY transaction_id
        $order->transaction_id = $apTransactionId;
        $order->save();
        
        Log::info('✅ QR: Transaction ID saved to order', [
            'order_no' => $order->order_no,
            'transaction_id' => $apTransactionId
        ]);
    } else {
        Log::warning('QR: No ap_transactionid in response data', [
            'response' => $decodedResponse
        ]);
    }
} else {
                Log::error('QR: Order not found: ' . $postedData['orderid']);
            }

            // Return full response to frontend (for QR code display)
            return response()->json([
                'status' => 'success',
                'response' => $decodedResponse
            ]);

        } catch (\Exception $e) {
            Log::error('QR: Exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate QR: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================
    // HELPER FUNCTIONS
    // =========================
    private function encrypt($data, $key)
    {
        $iv = bin2hex(openssl_random_pseudo_bytes(8));
        $raw = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $iv . base64_encode($raw);
    }

    private function decrypt($data, $key)
    {
        $iv = substr($data, 0, 16);
        $encryptedData = substr($data, 16);
        return openssl_decrypt(base64_decode($encryptedData), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    private function checksum($data)
    {
        ksort($data);
        $checksum = '';
        foreach ($data as $v) {
            $checksum .= $v;
        }
        return hash('sha256', $checksum . date('Y-m-d'));
    }
}