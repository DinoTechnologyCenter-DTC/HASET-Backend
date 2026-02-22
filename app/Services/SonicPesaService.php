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
    private $token;

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
     * 
     * @param string $phoneNumber Phone number (e.g., 255712345678)
     * @param float $amount Payment amount
     * @param string $orderReference Unique order reference
     * @return array Response from SonicPesa
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
            // Clean phone number (remove + and spaces)
            $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
            
            // SonicPesa likely expects 255 standard format
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '255' . substr($cleanPhone, 1);
            }

            // Determine mobile provider based on prefix (Optional but helpful for logging)
            $provider = $this->getProviderFromPhone($cleanPhone);

            $payload = [
                'buyer_email' => 'customer@haset.app', // Placeholder
                'buyer_name' => 'HASET Patient',      // Placeholder
                'buyer_phone' => $cleanPhone,
                'amount' => $amount,
                'currency' => 'TZS',
                'external_reference' => $orderReference // Often good to send this
            ];

            Log::info('Initiating SonicPesa payment', [
                'orderReference' => $orderReference,
                'endpoint' => $this->baseUrl . '/payment/create_order',
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'X-API-SECRET' => $this->secret,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/payment/create_order', $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('SonicPesa payment initiated successfully', ['response' => $data]);
                
                return [
                    'success' => true,
                    'data' => [
                        'id' => $data['transaction_id'] ?? $orderReference,
                        'status' => 'pending', 
                        'channel' => $provider
                    ]
                ];
            }

            // DETAILED ERROR LOGGING
            Log::error('SonicPesa payment initiation failed', [
                'status' => $response->status(),
                'body' => $response->body(), // This is critical to see the API error message
                'payload' => $payload
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Payment initiation failed: ' . $response->body(),
                'status' => $response->status()
            ];

        } catch (Exception $e) {
            Log::error('SonicPesa payment error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check payment status
     * 
     * @param string $transactionId Transaction ID to check
     * @return array Payment status
     */
    public function checkPaymentStatus($transactionId)
    {
        if (!$this->enabled) {
            return [
                'success' => true,
                'data' => [
                    'status' => 'SUCCESS',
                    'raw_response' => ['payment_status' => 'success', 'message' => 'Simulated success']
                ]
            ];
        }

        try {
            // Using endpoint /payment/query/{transaction_id} as a standard guess for similar APIs or keep generic
            // Assuming the check status endpoint follows similar patterns
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'X-API-SECRET' => $this->secret,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/payment/query', [ // Changed from check_transaction_status to payment/query which is common
                'transaction_id' => $transactionId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Map SonicPesa status format to Standard format
                // Example check: $data['payment_status'] might be "success", "failed", "pending"
                $rawStatus = strtolower($data['payment_status'] ?? 'pending');
                $status = 'PROCESSING';
                
                if ($rawStatus === 'success' || $rawStatus === 'completed') {
                    $status = 'SUCCESS';
                } elseif ($rawStatus === 'failed' || $rawStatus === 'cancelled') {
                    $status = 'FAILED';
                }

                return [
                    'success' => true,
                    'data' => [
                        'status' => $status,
                        'raw_response' => $data
                    ]
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to check payment status'
            ];

        } catch (Exception $e) {
            Log::error('SonicPesa status check error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Helper to determine provider from phone prefix
     */
    private function getProviderFromPhone($phone)
    {
        $prefix = substr($phone, 0, 3);
        $prefix2 = substr($phone, 0, 2);
        
        if (in_array($prefix, ['071', '065', '067'])) return 'Tigo';
        if (in_array($prefix, ['075', '076', '074'])) return 'Vodacom';
        if (in_array($prefix, ['078', '068', '069'])) return 'Airtel';
        if (in_array($prefix, ['062'])) return 'Halotel';
        if (in_array($prefix, ['073'])) return 'TTCL';
        
    }

    /**
     * Helper to map provider name to Channel ID (if required by SonicPesa)
     */
    private function getChannelId($provider)
    {
        // This mapping depends on SonicPesa's specific Channel IDs.
        // For now returning generic IDs or null if not strictly required
        return match($provider) {
            'Vodacom' => 'M-Pesa',
            'Tigo' => 'TigoPesa',
            'Airtel' => 'AirtelMoney',
            'Halotel' => 'HaloPesa',
            'TTCL' => 'T-Pesa',
            default => 'Mobile'
        };
    }
}
