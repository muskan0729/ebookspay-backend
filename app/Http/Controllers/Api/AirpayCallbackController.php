<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class AirpayCallbackController extends Controller
{

private $airpayKeys = [
    "353405" => "67d5c956c204bb6719bff713904d5bd7", // YES BANK //generated secret key

];    
    
private function decryptAirpay($encryptedResponse, $key)
{
    $iv  = substr($encryptedResponse, 0, 16);
    $enc = substr($encryptedResponse, 16);

    $dec = openssl_decrypt(
        base64_decode($enc),
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return $dec ? json_decode($dec, true) : null;
}

public function fireCallback($url, $postData)
{
    $logDir = storage_path('logs/airpay');
    $callbackLogFile = $logDir . '/merchant_callback_' . date('Y-m-d') . '.log';

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // 🔥 LOG EVERYTHING
    file_put_contents(
        $callbackLogFile,
        print_r([
            'time'       => now()->format('Y-m-d H:i:s'),
            'url'        => $url,
            'payload'    => $postData,
            'http_code'  => $httpCode,
            'response'   => $response,
            'curl_error' => $error ?: 'NONE'
        ], true) . "\n----------------------\n",
        FILE_APPEND
    );

    return $error ?: ($httpCode >= 400 ? "HTTP $httpCode" : null);
}


private function retryFailedCallbacks($file)
{
    if (!file_exists($file)) return;

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    file_put_contents($file, ''); // clear file

    foreach ($lines as $line) {
        $data = json_decode($line, true);
        if (!$data) continue;

       $this->fireCallback($data['url'], $data['payload']);
    }
}


    public function AirpayIpn(Request $request)
    {
        
        Log::info("airpay callback");
        $rawInput = $request->getContent();
        $payload  = json_decode($rawInput, true) ?: $request->all();

        $mid = $payload['merchant_id'] ?? null;

        // 🔑 Replace this with your actual merchant key mapping
        $airpayKeys = $this->airpayKeys;

        $key = ($mid && isset($airpayKeys[$mid])) ? $airpayKeys[$mid] : null;

        $decodedResponse = null;

        if ($key && !empty($payload['response'])) {
            $decodedResponse = $this->decryptAirpay($payload['response'], $key);
        }

        /*
        |--------------------------------------------------------------------------
        | Logging
        |--------------------------------------------------------------------------
        */
        $logDir = storage_path('logs/airpay');

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile       = $logDir . '/AIRpay_ipn_' . date('Y-m-d') . '.log';
        $failedLogFile = $logDir . '/failed_callbacks_ipn.log';

        file_put_contents(
            $logFile,
            print_r([
                'time'        => now()->format('Y-m-d H:i:s'),
                'merchant_id' => $mid,
                'raw_input'   => $rawInput,
                'decoded'     => $decodedResponse
            ], true) . "\n----------------------\n",
            FILE_APPEND
        );

        /*
        |--------------------------------------------------------------------------
        | IMMEDIATE ACK TO AIRPAY (VERY IMPORTANT)
        |--------------------------------------------------------------------------
        */
        response()->json(['status' => true])->send();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        /*
        |--------------------------------------------------------------------------
        | Retry Old Failed Callbacks
        |--------------------------------------------------------------------------
        */
        $this->retryFailedCallbacks($failedLogFile);

        /*
        |--------------------------------------------------------------------------
        | Send Merchant Callback (Fire & Forget)
        |--------------------------------------------------------------------------
        */
        $callbackUrl = 'https://dashboard.omishajewels.co.in/api/callback/update/prod/airpaycallbkp';

        $postData = [
            'decodedResponse' => $decodedResponse,
            'timestamp'       => now()->format('Y-m-d H:i:s'),
        ];

        $error = $this->fireCallback($callbackUrl, $postData);

        /*
        |--------------------------------------------------------------------------
        | If Failed → Store For Retry
        |--------------------------------------------------------------------------
        */
        if ($error) {
            file_put_contents(
                $failedLogFile,
                json_encode([
                    'time'    => now()->format('Y-m-d H:i:s'),
                    'url'     => $callbackUrl,
                    'payload' => $postData,
                    'error'   => $error
                ]) . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    public function Airpaycallback(Request $request)
    {
        
        // Log::info("airpay callback");
        $rawInput = $request->getContent();
        $payload  = json_decode($rawInput, true) ?: $request->all();

        $mid = $payload['merchant_id'] ?? null;

        // 🔑 Replace this with your actual merchant key mapping
        $airpayKeys = config('airpay.keys', []);

        $key = ($mid && isset($airpayKeys[$mid])) ? $airpayKeys[$mid] : null;

        $decodedResponse = null;

        if ($key && !empty($payload['response'])) {
            $decodedResponse = $this->decryptAirpay($payload['response'], $key);
        }

        /*
        |--------------------------------------------------------------------------
        | Logging
        |--------------------------------------------------------------------------
        */
        $logDir = storage_path('logs/airpay');

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile       = $logDir . '/AIRpay_callback_' . date('Y-m-d') . '.log';
        $failedLogFile = $logDir . '/failed_callbacks.log';

        file_put_contents(
            $logFile,
            print_r([
                'time'        => now()->format('Y-m-d H:i:s'),
                'merchant_id' => $mid,
                'raw_input'   => $rawInput,
                'decoded'     => $decodedResponse
            ], true) . "\n----------------------\n",
            FILE_APPEND
        );

        /*
        |--------------------------------------------------------------------------
        | IMMEDIATE ACK TO AIRPAY (VERY IMPORTANT)
        |--------------------------------------------------------------------------
        */
        response()->json(['status' => true])->send();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        /*
        |--------------------------------------------------------------------------
        | Retry Old Failed Callbacks
        |--------------------------------------------------------------------------
        */
        $this->retryFailedCallbacks($failedLogFile);

        /*
        |--------------------------------------------------------------------------
        | Send Merchant Callback (Fire & Forget)
        |--------------------------------------------------------------------------
        */
        $callbackUrl = 'https://live.spay.live/api/callback/update/prod/airpaycallbkp';

        $postData = [
            'decodedResponse' => $decodedResponse,
            'timestamp'       => now()->format('Y-m-d H:i:s'),
        ];

        $error = $this->fireCallback($callbackUrl, $postData);

        /*
        |--------------------------------------------------------------------------
        | If Failed → Store For Retry
        |--------------------------------------------------------------------------
        */
        if ($error) {
            file_put_contents(
                $failedLogFile,
                json_encode([
                    'time'    => now()->format('Y-m-d H:i:s'),
                    'url'     => $callbackUrl,
                    'payload' => $postData,
                    'error'   => $error
                ]) . PHP_EOL,
                FILE_APPEND
            );
        }
    }    
}