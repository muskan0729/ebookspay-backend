<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AirpayStatusController extends Controller
{
    protected $merchant_id;
    protected $username;
    protected $password;
    protected $client_id;
    protected $client_secret;
    protected $secret;
    protected $secretKey;
    
//     protected $merchant_id   = "353405";
// protected $username      = "zY4KPwTjP4";
// protected $password      = "dqQE4f8z";
// protected $client_id     = "454969";
// protected $client_secret = "4fbb61f1f5a95a242b14f4e44218dcc5";
// protected $secret        = "SRsB9xGfMFsgkzKt";
// protected $secretKey;

    public function __construct()
    {
        $this->merchant_id   = "353405";
        $this->username      ="zY4KPwTjP4";
        $this->password      = "dqQE4f8z";
        $this->client_id     ="454969";
        $this->client_secret = "4fbb61f1f5a95a242b14f4e44218dcc5";
        $this->secret        =  "SRsB9xGfMFsgkzKt";
        
        
        
        // $this->merchant_id   = "352568";
        // $this->username      = "UB2uYkYSYM";
        // $this->password      = "Yst9qn9A";
        // $this->client_id     = "179754";
        // $this->client_secret = "1d290bf8c8d70aa6d05f68cebfd3e9f1";
        // $this->secret        = "1d290bf8c8d70aa6d05f68cebfd3e9f1";

        

        $this->secretKey = md5($this->username . "~:~" . $this->password);
        
    }

    /* =========================
      ENCRYPT
    ========================= */
    private function encrypt($data, $key)
    {
        $iv = bin2hex(openssl_random_pseudo_bytes(8)); // 16 chars IV
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

        return hash('SHA256', $str . date('Y-m-d'));
    }

    /* =========================
      STEP 1 – GET ACCESS TOKEN
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
            return ['error' => curl_error($ch)];
        }

        curl_close($ch);

        $tokenArr = json_decode($response, true);

        if (!isset($tokenArr['response'])) {
            return ['error' => 'Invalid token response', 'raw' => $response];
        }

        $decrypted = $this->decrypt($tokenArr['response'], $this->secretKey);
        $tokenData = json_decode($decrypted, true);

        if (empty($tokenData['data']['access_token'])) {
            return ['error' => 'Token generation failed', 'raw' => $decrypted];
        }

        return $tokenData['data']['access_token'];
    }

    /* =========================
      STEP 2 – VERIFY STATUS
    ========================= */
    public function checkStatus(Request $request)
    {
        // dd($request);
        $request->validate([
            'ap_transactionid' => 'required'
        ]);
        $accessToken = $this->getAccessToken();

        if (!is_string($accessToken)) {
            return response()->json($accessToken);
        }

        $privatekey = hash(
            'SHA256',
            $this->secret . '@' . $this->username . ':|:' . $this->password
        );

        $statusData = [
            'merchant_id'      => $this->merchant_id,
            'ap_transactionid' => $request->ap_transactionid
        ];

        $payload = [
            'merchant_id' => $this->merchant_id,
            'encdata'     => $this->encrypt(json_encode($statusData), $this->secretKey),
            'checksum'    => $this->checksum($statusData),
            'privatekey'  => $privatekey
        ];
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
            return response()->json(['error' => curl_error($ch)]);
        }

        curl_close($ch);

        $statusArr = json_decode($response, true);

        if (!isset($statusArr['response'])) {
            return response()->json(['error' => 'Invalid status response', 'raw' => $response]);
        }

        $decrypted = $this->decrypt($statusArr['response'], $this->secretKey);
        $finalData = json_decode($decrypted, true);
        return response()->json($finalData);
    }
}