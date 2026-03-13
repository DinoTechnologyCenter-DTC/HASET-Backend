<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ZenoPaymentService
{
    private $apiKey;
    private $baseUrl;
    private $enabled;

    public function __construct()
    {
        $this->apiKey = config('services.zeno.api_key');
        $this->baseUrl = config('services.zeno.base_url');
        $this->enabled = config('services.zeno.enabled');
    }

    /**
     * Initiate Mobile Money Payment (USSD Push)
     */
    public function initiatePayment($phoneNumber, $amount, $orderReference)
    {
        if (!$this->enabled) {
            Log::info('Zeno is disabled, simulating payment');
            return [
                'success' => true,
                'simulated' => true,
                'data' => [
                    'id' => 'SIM_' . uniqid(),
                    'status' => 'PENDING',
                    'reference' => $orderReference,
                    'message' => 'Payment simulated (Zeno disabled)'
                ]
            ];
        }

        try {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '0' . substr($cleanPhone, 1); // Keep 0 prefix for Tanzania
            }

            $payload = [
                'order_id' => $orderReference,
                'buyer_email' => 'customer@haset.app',
                'buyer_name' => 'HASET Patient',
                'buyer_phone' => $cleanPhone,
                'amount' => $amount,
            ];

            Log::info('Initiating Zeno payment', ['reference' => $orderReference, 'phone' => $cleanPhone]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout(30)
            ->post($this->baseUrl . '/mobile_money/initiate', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'data' => [
                        'id' => $data['order_id'] ?? $orderReference,
                        'reference' => $data['reference'] ?? $orderReference,
                        'status' => strtoupper($data['status'] ?? 'PENDING'),
                        'channel' => 'Mobile Money'
                    ]
                ];
            }

            Log::error('Zeno payment initiation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload_sent' => $payload,
                'headers_sent' => ['x-api-key' => 'Bearer ' . substr($this->apiKey, 0, 10) . '...']
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Payment initiation failed',
                'status' => $response->status()
            ];

        } catch (Exception $e) {
            Log::error('Zeno payment error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Payment gateway error'
            ];
        }
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus($orderId)
    {
        if (!$this->enabled) {
            return [
                'success' => true,
                'data' => ['status' => 'COMPLETED']
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout(10)
            ->post($this->baseUrl . '/mobile_money/status', [
                'order_id' => $orderId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $rawStatus = strtoupper($data['payment_status'] ?? 'PENDING');
                
                // Normalize status
                $status = match($rawStatus) {
                    'COMPLETED', 'SUCCESS', 'SUCCESSFUL' => 'COMPLETED',
                    'FAILED', 'CANCELLED', 'REJECTED' => 'CANCELLED',
                    'PENDING', 'PROCESSING' => 'PENDING',
                    default => 'PENDING'
                };

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
            Log::error('Zeno status check error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Network error'];
        }
    }

    /**
     * Disburse Funds (Payout)
     */
    public function payout($phoneNumber, $amount, $orderReference)
    {
        if (!$this->enabled) {
            Log::info('Zeno Payout disabled, simulating success');
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
                'phone_number' => $cleanPhone,
                'amount' => $amount,
                'currency' => 'TZS',
                'reference' => $orderReference,
                'description' => 'Doctor Withdrawal via HASET App'
            ];

            Log::info('Initiating Zeno payout', ['payload' => $payload]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout(30)
            ->post($this->baseUrl . '/mobile_money/payout', $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error('Zeno payout failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'error' => 'Payout failed at gateway'];

        } catch (Exception $e) {
            Log::error('Zeno payout exception: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Network error during payout'];
        }
    }

    /**
     * Get Account Balance
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
            Log::info('Checking Zeno account balance');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json'
            ])
            ->timeout(10)
            ->get($this->baseUrl . '/account/balance');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => [
                        'balance' => floatval($data['data']['balance'] ?? $data['balance'] ?? 0.0),
                        'currency' => $data['data']['currency'] ?? $data['currency'] ?? 'TZS'
                    ]
                ];
            }

            Log::error('Zeno balance check failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Gateway Error: ' . $response->status()
            ];

        } catch (Exception $e) {
            Log::error('Zeno balance exception: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Network error during balance check'];
        }
    }
}
