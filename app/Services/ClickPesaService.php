<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ClickPesaService
{
    private $clientId;
    private $apiKey;
    private $baseUrl;
    private $enabled;
    private $token;

    public function __construct()
    {
        $this->clientId = env('CLICKPESA_CLIENT_ID');
        $this->apiKey = env('CLICKPESA_API_KEY');
        $this->baseUrl = env('CLICKPESA_BASE_URL', 'https://api.clickpesa.com');
        $this->enabled = env('CLICKPESA_ENABLED', false);
    }

    /**
     * Generate authorization token from ClickPesa
     */
    public function generateToken()
    {
        try {
            $response = Http::withHeaders([
                'client-id' => $this->clientId,
                'api-key' => $this->apiKey,
            ])->post($this->baseUrl . '/third-parties/generate-token');

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['success']) && $data['success'] && isset($data['token'])) {
                    $this->token = $data['token'];
                    Log::info('ClickPesa token generated successfully');
                    return $this->token;
                }
            }

            Log::error('ClickPesa token generation failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            throw new Exception('Failed to generate ClickPesa token');
        } catch (Exception $e) {
            Log::error('ClickPesa token generation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Initiate USSD-PUSH payment request
     * 
     * @param string $phoneNumber Phone number with country code (e.g., 255712345678)
     * @param float $amount Payment amount
     * @param string $orderReference Unique order reference
     * @return array Response from ClickPesa
     */
    public function initiatePayment($phoneNumber, $amount, $orderReference)
    {
        if (!$this->enabled) {
            Log::info('ClickPesa is disabled, simulating payment');
            return [
                'success' => true,
                'simulated' => true,
                'id' => 'SIM_' . uniqid(),
                'status' => 'PROCESSING',
                'orderReference' => $orderReference,
                'message' => 'Payment simulated (ClickPesa disabled)'
            ];
        }

        try {
            // Generate token if not already available
            if (!$this->token) {
                $this->generateToken();
            }

            // Clean phone number (remove + and spaces)
            $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
            
            // Ensure it starts with country code
            if (!str_starts_with($cleanPhone, '255')) {
                // If it starts with 0, replace with 255
                if (str_starts_with($cleanPhone, '0')) {
                    $cleanPhone = '255' . substr($cleanPhone, 1);
                } else {
                    $cleanPhone = '255' . $cleanPhone;
                }
            }

            $payload = [
                'amount' => (string) $amount,
                'currency' => 'TZS',
                'orderReference' => $orderReference,
                'phoneNumber' => $cleanPhone,
                // Checksum is optional - only if enabled in your ClickPesa settings
                // 'checksum' => $this->generateChecksum($payload)
            ];

            Log::info('Initiating ClickPesa payment', [
                'orderReference' => $orderReference,
                'amount' => $amount,
                'phoneNumber' => $cleanPhone
            ]);

            $response = Http::withHeaders([
                'Authorization' => $this->token,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/third-parties/payments/initiate-ussd-push-request', $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('ClickPesa payment initiated successfully', ['response' => $data]);
                return [
                    'success' => true,
                    'data' => $data
                ];
            }

            Log::error('ClickPesa payment initiation failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Payment initiation failed',
                'status' => $response->status()
            ];

        } catch (Exception $e) {
            Log::error('ClickPesa payment error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check payment status
     * 
     * @param string $orderReference Order reference to check
     * @return array Payment status
     */
    public function checkPaymentStatus($orderReference)
    {
        try {
            // Generate token if not already available
            if (!$this->token) {
                $this->generateToken();
            }

            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->get($this->baseUrl . '/third-parties/payments/query', [
                'orderReference' => $orderReference
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to check payment status'
            ];

        } catch (Exception $e) {
            Log::error('ClickPesa status check error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate checksum for payload (if required by ClickPesa settings)
     * Implement this based on ClickPesa documentation if checksum is enabled
     */
    private function generateChecksum($payload)
    {
        // TODO: Implement checksum generation if required
        // See: https://docs.clickpesa.com/home/checksum
        return null;
    }
}
