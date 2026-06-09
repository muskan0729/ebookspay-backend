<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use paytm\paytmchecksum\PaytmChecksum;

class StatusCheckController extends Controller
{
    protected $mid = 'VxgrFh37517070395887';

    protected $merchantKey = 'fD&1N4kpZ0GH2%73';

    protected $clientId = 'C11';

    // =========================
    // CHECK PAYMENT STATUS
    // =========================
    public function checkStatus(Request $request)
    {
        try {

            $request->validate([
                'orderId' => 'required',
            ]);

            $orderId = $request->orderId;

            $body = [
                'mid' => $this->mid,
                'orderId' => $orderId,
            ];

            $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);

            // ✅ NEW SIGNATURE EVERY TIME
            $signature = PaytmChecksum::generateSignature($bodyJson, $this->merchantKey);

            $payload = [
                'head' => [
                    'clientId' => $this->clientId,
                    'version' => 'v1',
                    'signature' => $signature,
                ],
                'body' => $body,
            ];

            $url = 'https://secure.paytmpayments.com/v3/order/status';

            $response = $this->curlPost($url, $payload);

            $decoded = json_decode($response, true);

            Log::info('Paytm Status Response', $decoded ?? []);

            // =========================
            // HANDLE RESPONSE
            // =========================
            $status = $decoded['body']['resultInfo']['resultStatus'] ?? null;

            if ($status === 'TXN_SUCCESS') {
                return response()->json([
                    'status' => 'success',
                    'payment_status' => 'SUCCESS',
                    'data' => $decoded,
                ]);
            }

            if ($status === 'TXN_FAILURE') {
                return response()->json([
                    'status' => 'success',
                    'payment_status' => 'FAILED',
                    'data' => $decoded,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'payment_status' => 'PENDING',
                'data' => $decoded,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // =========================
    // CURL HELPER
    // =========================
    private function curlPost($url, $payload)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }

        curl_close($ch);

        return $result;
    }
}
