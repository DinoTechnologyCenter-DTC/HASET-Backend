<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SonicPesaService
{
    private $email;
    private $apiKey;
    private $secret;
    private $baseUrl;
    private $enabled;

    public function __construct()
    {
        $this->email = config('services.sonicpesa.email');
        $this->apiKey = config('services.sonicpesa.api_key');
        $this->secret = config('services.sonicpesa.secret');
        $this->baseUrl = config('services.sonicpesa.base_url');
        $this->enabled = config('services.sonicpesa.enabled');
    }

    /**
     * Initiate Mobile Money Payment (USSD Push)
     */
    public function initiatePayment($phoneNumber, $amount, $orderReference)
    {
        if (!$this->enabled) {
            Log::info('SonicPesa is disabled, simulating payment');
            return [
                'success' => true,
                'simulated' => true,
                'id' => 'SIM_' . uniqid(),
                'status' => 'pending',
                'orderReference' => $orderReference,
                'message' => 'Payment simulated (SonicPesa disabled)'
            ];
        }

        try {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '255' . substr($cleanPhone, 1);
            }

            $payload = [
                'buyer_email' => 'customer@haset.app',
                'buyer_name' => 'HASET Patient',
                'buyer_phone' => $cleanPhone,
                'amount' => $amount,
                'currency' => 'TZS',
                'external_reference' => $orderReference
            ];

            Log::info('Initiating SonicPesa payment', ['orderReference' => $orderReference]);

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'HASET-Payment/1.0'
            ])
            ->timeout(30)
            ->post($this->baseUrl . '/payment/create_order', $payload);

            if ($response->successful()) {
                $data = $response->json();
                $orderId = $data['data']['order_id'] ?? $orderReference;
                
                return [
                    'success' => true,
                    'data' => [
                        'id' => $orderId,
                        'reference' => $data['data']['reference'] ?? null,
                        'status' => $data['data']['status'] ?? 'PENDING',
                        'channel' => $data['data']['channel'] ?? 'Mobile'
                    ]
                ];
            }

            Log::error('SonicPesa payment initiation failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Payment initiation failed',
                'status' => $response->status()
            ];

        } catch (Exception $e) {
            Log::error('SonicPesa payment error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Payment gateway error'
            ];
        }
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus($transactionId)
    {
        if (!$this->enabled) {
            return [
                'success' => true,
                'data' => ['status' => 'SUCCESS']
            ];
        }

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout(10)
            ->post($this->baseUrl . '/payment/order_status', [
                'order_id' => $transactionId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $rawStatus = strtolower($data['data']['payment_status'] ?? 'pending');
                $status = 'PENDING';
                
                if ($rawStatus === 'completed') {
                    $status = 'COMPLETED';
                } elseif (in_array($rawStatus, ['cancelled', 'usercancelled', 'rejected'])) {
                    $status = 'CANCELLED';
                } elseif ($rawStatus === 'inprogress') {
                    $status = 'INPROGRESS';
                }

                return [
                    'success' => true,
                    'data' => [
                        'status' => $status,
                        'raw_response' => $data
                    ]
                ];
            }

            return ['success' => false, 'error' => 'Failed to check status'];

        } catch (Exception $e) {
            Log::error('SonicPesa status check error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Network error'];
        }
    }

    /**
     * Disburse Funds (Payout)
     */
    public function payout($phoneNumber, $amount, $orderReference)
    {
        if (!$this->enabled) {
            Log::info('SonicPesa Payout disabled, simulating success');
            return [
                'success' => true,
                'simulated' => true,
                'data' => [
                    'status' => 'SUCCESS',
                    'message' => 'Simulated payout success'
                ]
            ];
        }

        try {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '255' . substr($cleanPhone, 1);
            }

            $payload = [
                'recipient_phone' => $cleanPhone,
                'amount' => $amount,
                'currency' => 'TZS',
                'external_reference' => $orderReference,
                'remarks' => 'Doctor Withdrawal via HASET App'
            ];

            Log::info('Initiating SonicPesa payout attempt', ['payload' => $payload]);

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout(30)
            ->post($this->baseUrl . '/payment/payout', $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error('SonicPesa payout failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'error' => 'Payout failed at gateway'];

        } catch (Exception $e) {
            Log::error('SonicPesa payout exception: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Network error during payout'];
        }
    }

    /**
     * Get Account Balance (Platform/Gateway Balance)
     */
    public function getAccountBalance()
    {
        if (!$this->enabled) {
            return [
                'success' => true,
                'simulated' => true,
                'data' => [
                    'balance' => 4550200.0,
                    'currency' => 'TZS'
                ]
            ];
        }

        try {
            Log::info('Checking SonicPesa account balance');

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept' => 'application/json'
            ])
            ->timeout(10)
            ->get($this->baseUrl . '/payment/balance');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => [
                        'balance' => floatval($data['data']['balance'] ?? 0.0),
                        'currency' => $data['data']['currency'] ?? 'TZS'
                    ]
                ];
            }

            $errorBody = $response->json();
            Log::error('SonicPesa balance check failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => $errorBody['message'] ?? 'Gateway Error: ' . $response->status()
            ];

        } catch (Exception $e) {
            Log::error('SonicPesa balance exception: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Network error during balance check'];
        }
    }

    private function getProviderFromPhone($phone)
    {
        $prefix = substr($phone, 0, 3);
        if (in_array($prefix, ['071', '065', '067'])) return 'Tigo';
        if (in_array($prefix, ['075', '076', '074'])) return 'Vodacom';
        if (in_array($prefix, ['078', '068', '069'])) return 'Airtel';
        if (in_array($prefix, ['062'])) return 'Halotel';
        if (in_array($prefix, ['073'])) return 'TTCL';
        return 'Mobile';
    }
}
