<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AirpayController extends Controller
    
{
    // protected $merchant_id;
    // protected $username;
    // protected $password;
    // protected $client_id;
    // protected $client_secret;
    // protected $secretKey;
    // protected $secret;


// yes bank
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

            $this->merchant_id   = $data['merchant_id'] ?? null;
            $this->username      = $data['username'] ?? null;
            $this->password      = $data['password'] ?? null;
            $this->client_id     = $data['client_id'] ?? null;
            $this->client_secret = $data['client_secret'] ?? null;
            $this->secretKey     = $data['secretKey'] ?? null;
            $this->secret        = $data['secret'] ?? null;
         
         
            
        }
    }

    // =========================
    // GET ACCESS TOKEN
    // =========================
    public function getAccessToken()
    {
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

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://kraken.airpay.co.in/airpay/pay/v4/api/oauth2/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $payload
        ]);

        $result = curl_exec($curl);
        curl_close($curl);

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to Airpay token API'
            ], 500);
        }

        $response = json_decode($result)->response ?? null;

        $access_token_data = $this->decrypt($response, $this->secretKey);
        $resp = json_decode($access_token_data, true);

        if (isset($resp['data']['access_token'])) {
            return $resp['data']['access_token'];
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to get token',
            'response' => $resp
        ], 400);
    }

    // =========================
    // GENERATE QR
    // =========================
    public function generateQR(Request $request)
    {
        try {
  
            $postedData = $request->all();

            $dynamicFields = ['orderid', 'amount', 'buyer_email', 'buyer_phone'];

            foreach ($dynamicFields as $field) {
                if (empty($postedData[$field])) {
                    return response()->json([
                        'status' => 'error',
                        'status_code' => 400,
                        'message' => "Missing required field: $field"
                    ], 400);
                }
            }

            $access_token = $this->getAccessToken();

            if (!is_string($access_token)) {
                return $access_token;
            }

            $data = [
                'orderid' => $postedData['orderid'],
                'amount' => $postedData['amount'],
                'buyer_email' => $postedData['buyer_email'],
                'buyer_phone' => $postedData['buyer_phone'],
                'call_type' => 'upiqr',
                'mer_dom' => base64_encode("https://omishajewels.com"),
                'customer_consent' => 'Y'
            ];

            $privatekey = hash('sha256', $this->secret . '@' . $this->username . ':|:' . $this->password);

            $encdata  = $this->encrypt(json_encode($data), $this->secretKey);
            $checksum = $this->checksum($data);

            $payload = [
                'merchant_id' => $this->merchant_id,
                'encdata'     => $encdata,
                'checksum'    => $checksum,
                'privatekey'  => $privatekey
            ];

            $url = "https://kraken.airpay.co.in/airpay/pay/v4/api/generateorder/?token=" . $access_token;

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 30
            ]);

            $result = curl_exec($curl);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                return response()->json([
                    'status' => 'error',
                    'status_code' => 500,
                    'message' => 'CURL Error: ' . $curlError
                ], 500);
            }

            $response = json_decode($result, true);

            if (!isset($response['response'])) {
                return response()->json([
                    'status' => 'error',
                    'status_code' => 400,
                    'message' => 'Invalid QR response from Airpay',
                    'raw_response' => $result
                ], 400);
            }

            $decrypted = $this->decrypt($response['response'], $this->secretKey);
            $decodedResponse = json_decode($decrypted, true);

            return response()->json([
                'response' => $decodedResponse
            ]);

        } catch (Exception $e) {

            return response()->json([
                'status' => 'error',
                'status_code' => 500,
                'message' => 'Exception: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================
    // HELPER FUNCTIONS
    // =========================

    private function encrypt($data, $encryptionkey)
    {
        $iv = bin2hex(openssl_random_pseudo_bytes(8));
        $raw = openssl_encrypt($data, 'AES-256-CBC', $encryptionkey, OPENSSL_RAW_DATA, $iv);
        return $iv . base64_encode($raw);
    }

    private function decrypt($response, $encryptionkey)
    {
        $iv = substr($response, 0, 16);
        $encryptedData = substr($response, 16);
        return openssl_decrypt(base64_decode($encryptedData), 'AES-256-CBC', $encryptionkey, OPENSSL_RAW_DATA, $iv);
    }

    private function checksum($data)
    {
        ksort($data);
        $checksumdata = '';
        foreach ($data as $value) {
            $checksumdata .= $value;
        }
        return hash('SHA256', $checksumdata . date('Y-m-d'));
    }
    
public function checkStatus(Request $request)
{
    // STEP 1: Get Token
    // dd($request->all());
    $accessToken = $this->getAccessToken();
  dump($accessToken);
    if (!is_string($accessToken)) {
        return $accessToken;
    }

    // STEP 2: Generate private key
    $privatekey = hash(
        'SHA256',
        $this->secret . '@' . $this->username . ':|:' . $this->password
    );

    $ap_transactionid = $request->ap_transactionid;
dump($ap_transactionid);
    $statusData = [
        'merchant_id'      => $this->merchant_id,
        'ap_transactionid' => $ap_transactionid,
    ];

    $statusPayload = [
        'merchant_id' => $this->merchant_id,
        'encdata'     => $this->encrypt(json_encode($statusData), $this->secretKey),
        'checksum'    => $this->checksum($statusData),
        'privatekey'  => $privatekey
    ];

    $url = "https://kraken.airpay.co.in/airpay/pay/v4/api/verify/?token=" . $accessToken;

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($statusPayload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/x-www-form-urlencoded"
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $statusResponse = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);

        return response()->json([
            'status' => 'error',
            'message' => 'Curl Error',
            'error' => $error
        ], 500);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
        return response()->json([
            'status' => 'error',
            'message' => 'Airpay API failed',
            'http_code' => $httpCode,
            'raw' => $statusResponse
        ], 400);
    }

    $statusArr = json_decode($statusResponse, true);

    if (!isset($statusArr['response'])) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid status response',
            'raw' => $statusResponse
        ], 400);
    }

    $finalDecrypted = $this->decrypt($statusArr['response'], $this->secretKey);
    $finalResponse  = json_decode($finalDecrypted, true);

    return response()->json([
        'response' => $finalResponse
    ]);
}




}