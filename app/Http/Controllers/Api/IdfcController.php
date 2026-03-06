<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IdfcController extends Controller
    
{


    // =========================
    // GET ACCESS TOKEN
    // =========================
    public function getAccessToken()
    {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://app.altrix.dev/payout/generate/token?merchant_id=VDVL',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $result = curl_exec($curl);
        curl_close($curl);

//   dump($result);
       
        // return response()->json([
        //     'response' => $result
        // ], 400);
        return $result;
    }

    // =========================
    // GENERATE QR
    // =========== ==============
    public function generateQR(Request $request)
    {
    try{
             $request->validate([
                'beneficiary_name' => 'required|string',
                'beneficiary_account_number' => 'required|string',
                'beneficiary_ifsc_code' => 'required|string',
                'beneficiary_bank_name' => 'required|string',
                'beneficiary_mobile' => 'required|digits_between:10,15',
                'amount' => 'required|numeric|min:1',
                'merchant_txn_id' => 'required|string'
            ]);
            
          $access_token = $this->getAccessToken();

            if (!is_string($access_token)) {
                return $access_token;
            }
            
        $payload = [
            [
                "mid" => "VDVL",
                "beneficiary_name" => $request->beneficiary_name,
                "beneficiary_account_number" => $request->beneficiary_account_number,
                "beneficiary_ifsc_code" => $request->beneficiary_ifsc_code,
                "beneficiary_bank_name" => $request->beneficiary_bank_name,
                "beneficiary_mobile" => $request->beneficiary_mobile,
                "amount" => $request->amount,
                "merchant_txn_id" => $request->merchant_txn_id,
            ]
        ];

        $curl = curl_init();
        
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://app.altrix.dev/payout/bulk/payout',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$access_token}",
                "Content-Type: application/json",
            ],
        ));
        
        $response = curl_exec($curl);
        // dd($response);
            // if (curl_errno($curl)) {
            //     dd('Curl Error: ' . curl_error($curl));
            // }
            $decoded = json_decode($response);
            // $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            // dd($decoded);
            curl_close($curl);
            return response()->json([
                "response" => $decoded
            ]);
    } catch (Exception $e) {

            return response()->json([
                'status' => 'error',
                'status_code' => 500,
                'message' => 'Exception: ' . $e->getMessage()
            ], 500);
        }      
    }







}