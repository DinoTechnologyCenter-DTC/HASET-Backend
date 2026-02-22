<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function initiate(Request $request)
    {
        // 1. Validate Input
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'doctor_id' => 'required|string',
            'amount' => 'required|numeric|min:50|max:5000000',
            'provider' => 'required|string',
            'payment_account' => 'required|string',
        ]);

        // 1.5 Prevent duplicate initiation (de-bounce)
        // Check if there is already a processing transaction for this user/doctor in the last 2 minutes
        $existing = Transaction::where('user_id', $validated['user_id'])
            ->where('doctor_id', $validated['doctor_id'])
            ->whereIn('status', ['pending', 'processing'])
            ->where('created_at', '>', now()->subMinutes(2))
            ->first();

        if ($existing) {
            Log::warning('Duplicate payment initiation blocked', [
                'user_id' => $validated['user_id'],
                'transaction_id' => $existing->id
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'A payment request is already active for this doctor. Please wait for the USSD prompt on your phone.',
                'transaction_id' => $existing->id
            ], 429); // 429 Too Many Requests
        }

        // 2. Create local transaction record
        $transaction = Transaction::create([
            'user_id' => $validated['user_id'] ?? null,
            'doctor_id' => $validated['doctor_id'],
            'amount' => $validated['amount'],
            'currency' => 'TZS',
            'provider' => $validated['provider'],
            'payment_account' => $validated['payment_account'],
            'status' => 'pending',
            'description' => 'Consultation Fee Payment',
        ]);

        // 3. Integrate with SonicPesa Payment Gateway
        try {
            $sonicPesa = new \App\Services\SonicPesaService();
            
            // Generate unique order reference
            $orderReference = 'HASET' . $transaction->id . 'T' . time();
            
            // Initiate payment with SonicPesa
            $paymentResult = $sonicPesa->initiatePayment(
                $validated['payment_account'],
                $validated['amount'],
                $orderReference
            );

            if ($paymentResult['success']) {
                // Update transaction with SonicPesa reference
                $transaction->update([
                    'external_reference' => $paymentResult['data']['id'] ?? $orderReference,
                    'status' => 'processing'
                ]);

                Log::info('Payment initiated successfully', [
                    'transaction_id' => $transaction->id,
                    'sonicpesa_id' => $paymentResult['data']['id'] ?? null,
                    'order_reference' => $orderReference
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment initiated successfully. Please check your phone to complete the payment.',
                    'transaction_id' => $transaction->id,
                    'order_reference' => $orderReference,
                    'sonicpesa_status' => $paymentResult['data']['status'] ?? 'pending',
                    'payment_channel' => $paymentResult['data']['channel'] ?? $validated['provider']
                ], 200);
            } else {
                // Payment initiation failed
                $transaction->update(['status' => 'failed']);
                
                Log::error('SonicPesa payment initiation failed', [
                    'transaction_id' => $transaction->id,
                    'error' => $paymentResult['error'] ?? 'Unknown error'
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => $paymentResult['error'] ?? 'Payment initiation failed',
                    'transaction_id' => $transaction->id
                ], 400);
            }
        } catch (\Exception $e) {
            // Handle any exceptions
            $transaction->update(['status' => 'failed']);
            
            Log::error('Payment processing exception', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while processing payment: ' . $e->getMessage(),
                'transaction_id' => $transaction->id
            ], 500);
        }
    }
    
    public function callback(Request $request)
    {
        // Handle webhook callbacks from SonicPesa
        Log::info('SonicPesa Payment Callback Received:', $request->all());
        
        try {
            // SonicPesa typically sends transaction_id and payment_status
            $transactionId = $request->input('transaction_id'); // External ID
            $status = strtolower($request->input('payment_status')); // success, failed
            
            if ($transactionId) {
                // Find transaction by external reference
                $transaction = Transaction::where('external_reference', $transactionId)->first();
                
                if ($transaction) {
                    // Map Status
                    $newStatus = match($status) {
                        'success', 'completed' => 'success',
                        'failed', 'cancelled' => 'failed',
                        default => 'pending'
                    };
                    
                    if ($transaction->status !== 'success') { // Don't overlook already successful ones
                        $transaction->update([
                            'status' => $newStatus
                        ]);
                        
                        Log::info('Transaction status updated via callback', [
                            'transaction_id' => $transaction->id,
                            'new_status' => $newStatus
                        ]);
                    }
                }
            }
            
            return response()->json(['status' => 'received'], 200);
        } catch (\Exception $e) {
            Log::error('Callback processing error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }
    
    public function checkStatus(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|integer|exists:transactions,id'
        ]);
        
        $transaction = Transaction::find($validated['transaction_id']);
        
        if (!$transaction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found'
            ], 404);
        }
        
        // If transaction is already completed, return current status
        if (in_array($transaction->status, ['success', 'failed'])) {
            return response()->json([
                'status' => 'success',
                'transaction' => [
                    'id' => $transaction->id,
                    'status' => $transaction->status,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'provider' => $transaction->provider,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at
                ]
            ], 200);
        }
        
        // Check status with SonicPesa
        try {
            $sonicPesa = new \App\Services\SonicPesaService();
            // Use external reference (transaction ID from SonicPesa) if available, otherwise fallback might fail
            // SonicPesa usually needs the ID they returned. Transaction model stores it in external_reference.
            $result = $sonicPesa->checkPaymentStatus($transaction->external_reference);
            
            if ($result['success'] && isset($result['data']['status'])) {
                // Update transaction status
                $sonicPesaStatus = $result['data']['status']; // ALREADY MAPPED TO UPPERCASE OR STANDARD IN SERVICE
                
                // Map again just to be safe if service returns RAW status or standard one
                $newStatus = match(strtoupper($sonicPesaStatus)) {
                    'SUCCESS', 'COMPLETED' => 'success',
                    'FAILED', 'CANCELLED' => 'failed',
                    'PROCESSING', 'PENDING' => 'pending',
                    default => 'pending'
                };
                
                $transaction->update(['status' => $newStatus]);
                
                return response()->json([
                    'status' => 'success',
                    'transaction' => [
                        'id' => $transaction->id,
                        'status' => $newStatus,
                        'sonicpesa_status' => $sonicPesaStatus,
                        'amount' => $transaction->amount,
                        'currency' => $transaction->currency,
                        'provider' => $transaction->provider,
                        'created_at' => $transaction->created_at,
                        'updated_at' => $transaction->updated_at
                    ]
                ], 200);
            }
            
            return response()->json([
                'status' => 'success', // Request succeeded, but maybe payment is still pending locally
                'transaction' => [
                    'id' => $transaction->id,
                    'status' => $transaction->status,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'provider' => $transaction->provider
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Status check error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check payment status',
                'transaction' => [
                    'id' => $transaction->id,
                    'status' => $transaction->status
                ]
            ], 500);
        }
    }

    public function cancel(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|integer|exists:transactions,id'
        ]);

        $transaction = Transaction::find($validated['transaction_id']);

        if ($transaction && !in_array($transaction->status, ['success', 'failed'])) {
            $transaction->update(['status' => 'failed', 'description' => $transaction->description . ' (Cancelled by User)']);
            Log::info('Transaction explicitly cancelled by user', ['transaction_id' => $transaction->id]);
            return response()->json(['status' => 'success', 'message' => 'Transaction cancelled'], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Transaction cannot be cancelled or already finished'], 400);
    }
}
