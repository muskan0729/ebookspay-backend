<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class IdfcCallbackController extends Controller
{

public function fireCallback($url, $postData)
{
    $logDir = storage_path('logs/Idfc');
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

    // ðŸ”¥ LOG EVERYTHING
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

public function IdfcIpn(Request $request)
{
    // dd("hello");
    Log::info("IDFC callback received");

    $rawInput = $request->getContent();
    $payload  = json_decode($rawInput, true) ?: $request->all();

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    $logDir = storage_path('logs/idfc');

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile       = $logDir . '/IDFC_ipn_' . date('Y-m-d') . '.log';
    $failedLogFile = $logDir . '/failed_callbacks_idfc.log';

    file_put_contents(
        $logFile,
        print_r([
            'time'      => now()->format('Y-m-d H:i:s'),
            'raw_input' => $rawInput,
            'payload'   => $payload
        ], true) . "\n----------------------\n",
        FILE_APPEND
    );

    /*
    |--------------------------------------------------------------------------
    | IMMEDIATE ACK TO IDFC
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
    | Send Merchant Callback
    |--------------------------------------------------------------------------
    */
    $callbackUrl = 'https://dashboard.omishajewels.co.in/api/callback/update/prod/IdfcCallback';

    $postData = [
        'provider'  => 'IDFC',
        'payload'   => $payload,
        'timestamp' => now()->format('Y-m-d H:i:s'),
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