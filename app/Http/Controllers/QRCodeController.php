<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use paytm\paytmchecksum\PaytmChecksum;

class QRCodeController extends Controller
{
    protected $mid = 'VxgrFh37517070395887';

    protected $merchantKey = 'fD&1N4kpZ0GH2%73';

    protected $clientId = 'C11';

    // =========================
    // GENERATE QR
    // =========================
    public function generateQR(Request $request)
    {
        try {

            $request->validate([
                'orderid' => 'required',
                'amount' => 'required',
            ]);

            $orderId = $request->orderid;

            $body = [
                'mid' => $this->mid,
                'orderId' => $orderId,
                'amount' => (string) $request->amount,
                'businessType' => 'UPI_QR_CODE',
                'imageRequired' => true,
                'additionalInfo' => [
                    'udf1' => $request->udf1 ?? 'NA',
                ],
            ];

            // ⚠️ IMPORTANT: JSON MUST BE EXACT
            $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);

            // ✅ SIGNATURE (always fresh)
            $signature = PaytmChecksum::generateSignature($bodyJson, $this->merchantKey);

            $payload = [
                'head' => [
                    'clientId' => $this->clientId,
                    'version' => 'v1',
                    'channelId' => 'WEB',
                    'requestTimestamp' => (string) round(microtime(true) * 1000),
                    'signature' => $signature,
                ],
                'body' => $body,
            ];

            $url = 'https://secure.paytmpayments.com/theia/api/v1/qr/create';

            $response = $this->curlPost($url, $payload);

            $decoded = json_decode($response, true);

            Log::info('Paytm QR Response', $decoded ?? []);

            return response()->json([
                'status' => 'success',
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
