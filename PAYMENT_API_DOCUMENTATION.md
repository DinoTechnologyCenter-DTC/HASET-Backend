# HASET Payment Backend API Documentation

## Overview
This Laravel backend service handles payment processing for the HASET mobile application using the **SonicPesa** payment gateway. It provides endpoints for initiating USSD push payments, checking payment status, and handling callbacks.

**Base URL:** `https://hasethospital.or.tz/api/` (Production)
**Base URL:** `https://your-ngrok-url.ngrok-free.dev/api/` (Development)

---

## Payment Flow

```
┌─────────┐     POST /initiate      ┌─────────┐     USSD Push      ┌─────────┐
│  App    │ ──────────────────────► │ Backend │ ────────────────► │ Sonic   │
│         │ ◄────────────────────── │         │ ◄─────────────── │ Pesa    │
└─────────┘                         └─────────┘                   └─────────┘
      │                                   │                              │
      │      GET /status (every 6s)      │                              │
      │ ─────────────────────────────────►│                              │
      │ ◄─────────────────────────────────│                              │
      │         (polls until success)     │                              │
```

---

## Endpoints

### 1. Initiate Payment

**Endpoint:** `POST /payment/initiate`

Initiates a USSD push payment to the customer's phone.

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "user_id": "firebase_user_uid_123",
  "doctor_id": "doc_123",
  "amount": 10000,
  "provider": "Mpesa",
  "payment_account": "255712345678"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `user_id` | string | No | Firebase UID of the patient |
| `doctor_id` | string | Yes | Doctor's Firebase UID |
| `amount` | number | Yes | Amount in TZS (min: 50, max: 5,000,000) |
| `provider` | string | Yes | Provider name (Mpesa, TigoPesa, AirtelMoney, HaloPesa) |
| `payment_account` | string | Yes | Mobile number (255XXXXXXXXX format) |

**Success Response (200 OK):**
```json
{
  "status": "success",
  "message": "Payment initiated successfully. Please check your phone to complete the payment.",
  "transaction_id": 1,
  "order_reference": "HASET66T1234567890",
  "sonicpesa_status": "PENDING",
  "payment_channel": "M-Pesa"
}
```

**Error Response - Duplicate Request (429):**
```json
{
  "status": "error",
  "message": "A payment request is already active for this doctor. Please wait for the USSD prompt on your phone.",
  "transaction_id": 1
}
```

**Error Response - Validation (422):**
```json
{
  "message": "The amount field is required.",
  "errors": {
    "amount": ["The amount field is required."]
  }
}
```

**Example cURL:**
```bash
curl -X POST https://hasethospital.or.tz/api/payment/initiate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "user_id": "firebase_user_123",
    "doctor_id": "doc_456",
    "amount": 15000,
    "provider": "Mpesa",
    "payment_account": "255712345678"
  }'
```

---

### 2. Check Payment Status

**Endpoint:** `GET /payment/status?transaction_id={id}`

Poll this endpoint to check if payment has been completed. The Android app polls every 6 seconds (max 20 attempts = 2 minutes).

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `transaction_id` | integer | Yes | Transaction ID from initiate response |

**Success Response (200 OK):**
```json
{
  "status": "success",
  "transaction": {
    "id": 1,
    "status": "pending",
    "amount": 15000,
    "currency": "TZS",
    "provider": "Mpesa",
    "created_at": "2026-02-23T12:00:00Z",
    "updated_at": "2026-02-23T12:00:05Z"
  }
}
```

**Status Values (from SonicPesa):**
| Status | Description |
|--------|-------------|
| `PENDING` | Payment initiated, waiting for user to complete USSD on phone |
| `INPROGRESS` | Payment is being processed |
| `COMPLETED` | Payment completed successfully |
| `CANCELLED` | Payment was cancelled |
| `USERCANCELLED` | User cancelled the payment on their phone |
| `REJECTED` | Payment was rejected |

**Local Status Mapping:**
| SonicPesa Status | Local Status |
|------------------|---------------|
| COMPLETED | success |
| PENDING, INPROGRESS | pending |
| CANCELLED, USERCANCELLED, REJECTED | failed |

---

### 3. Payment Callback (Webhook)

**Endpoint:** `POST /payment/callback`

Receives webhook notifications from SonicPesa when payment status changes. This is the most reliable way to receive payment confirmation.

**Headers:**
```
Content-Type: application/json
X-SonicPesa-Signature: <signature_for_verification>
```

**Request Body (from SonicPesa):**
```json
{
  "event": "payment.completed",
  "order_id": "sp_67890abcdef",
  "amount": 10000,
  "currency": "TZS",
  "status": "SUCCESS",
  "transid": "TXN123456",
  "channel": "AIRTELMONEY",
  "reference": "0289999288",
  "msisdn": "255682812345",
  "timestamp": "2025-01-07T12:05:00Z"
}
```

**Success Response (200 OK):**
```json
{
  "status": "received"
}
```

---

### 4. Withdraw Funds (Payout)

**Endpoint:** `POST /payment/payout`

Initiates a fund disbursement (payout) to a doctor's mobile money account. This is usually triggered by an admin approval.

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "request_id": "withdraw_123",
  "doctor_id": "doc_456",
  "amount": 50000,
  "phone_number": "255712345678",
  "provider": "Mpesa",
  "admin_id": "admin_uid_789",
  "password": "YOUR_ADMIN_PASSWORD"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `request_id` | string | Yes | The ID of the withdrawal request |
| `doctor_id` | string | Yes | Doctor's Firebase UID |
| `amount` | number | Yes | Amount to disburse |
| `phone_number` | string | Yes | Recipient's mobile number (255XXXXXXXXX format) |
| `provider` | string | Yes | Provider name (Mpesa, TigoPesa, etc.) |
| `admin_id` | string | Yes | Firebase UID of the admin performing the action |
| `password` | string | Yes | Admin payout password for verification |

**Success Response (200 OK):**
```json
{
  "status": "success",
  "message": "Payout initiated successfully",
  "transaction_id": 45,
  "sonicpesa_response": { ... }
}
```

**Error Response - Invalid Password (403):**
```json
{
  "status": "error",
  "message": "Invalid admin password"
}
```

---

### 5. Cancel Payment

**Endpoint:** `POST /payment/cancel`

Cancels an active payment transaction (when user aborts).

**Request Body:**
```json
{
  "transaction_id": 1
}
```

**Success Response (200 OK):**
```json
{
  "status": "success",
  "message": "Transaction cancelled"
}
```

**Error Response (400):**
```json
{
  "status": "error",
  "message": "Transaction cannot be cancelled or already finished"
}
```

---

## Database Schema

### Transactions Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | string (nullable) | Firebase UID of the patient |
| `doctor_id` | string | Firebase UID of the doctor |
| `amount` | decimal(12,2) | Payment amount in TZS |
| `currency` | string | Currency code (default: "TZS") |
| `provider` | string | Payment provider name |
| `payment_account` | string | Phone number used for payment |
| `status` | string | pending/processing/success/failed |
| `external_reference` | string | SonicPesa order_id |
| `description` | string | Transaction description |
| `created_at` | timestamp | Record creation time |
| `updated_at` | timestamp | Last update time |

---

## Environment Configuration

Create/update `.env` file:

```env
# SonicPesa Payment Gateway
SONICPESA_ENABLED=true
SONICPESA_API_KEY=sk_live_xxxxxxxxxxxxxxxxxxxx
SONICPESA_SECRET=xxxxxxxxxxxxxxxxxxxx
SONICPESA_BASE_URL=https://api.sonicpesa.com/api/v1
```

**Configuration Details:**

| Variable | Description |
|----------|-------------|
| `SONICPESA_ENABLED` | Set to `false` for simulation mode (always returns success) |
| `SONICPESA_API_KEY` | Your SonicPesa API key (get from dashboard) |
| `SONICPESA_SECRET` | Your SonicPesa secret key |
| `SONICPESA_BASE_URL` | API base URL (default: https://api.sonicpesa.com/api/v1) |

---

## Android Integration

### API Service Interface

```java
package com.haset.hasetapp.api;

import com.haset.hasetapp.api.requests.PaymentRequest;
import com.haset.hasetapp.api.responses.PaymentResponse;
import com.haset.hasetapp.api.responses.PaymentStatusResponse;

import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.Query;

public interface PaymentApiService {
    @POST("payment/initiate")
    Call<PaymentResponse> initiatePayment(@Body PaymentRequest request);

    @GET("payment/status")
    Call<PaymentStatusResponse> checkPaymentStatus(@Query("transaction_id") int transactionId);

    @POST("payment/cancel")
    Call<Void> cancelPayment(@Query("transaction_id") int transactionId);
}
```

### Request/Response Models

**PaymentRequest.java:**
```java
public class PaymentRequest {
    private String user_id;
    private String doctor_id;
    private double amount;
    private String provider;
    private String payment_account;
    // Constructors, getters, setters
}
```

**PaymentResponse.java:**
```java
public class PaymentResponse {
    private String status;
    private String message;
    private int transaction_id;
    private String order_reference;
    private String sonicpesa_status;
    // Getters
}
```

**PaymentStatusResponse.java:**
```java
public class PaymentStatusResponse {
    private String status;
    private Transaction transaction;

    public class Transaction {
        private int id;
        private String status;  // pending, processing, success, failed
        private double amount;
        private String currency;
        private String provider;

        public boolean isSuccess() { return "success".equals(status); }
        public boolean isFailed() { return "failed".equals(status); }
        public boolean isProcessing() { return "pending".equals(status) || "processing".equals(status); }
    }
}
```

### Payment Repository (Polling Logic)

```java
public class PaymentRepository {
    private static final int STATUS_CHECK_INTERVAL = 6000; // 6 seconds
    private static final int MAX_STATUS_CHECKS = 20; // 2 minutes total

    private void pollPaymentStatus(int transactionId, String doctorId, double amount,
                                  int attemptCount, OnCompleteListener<Boolean> callback) {
        if (attemptCount >= MAX_STATUS_CHECKS) {
            isProcessingPayment = false;
            callback.onError("Payment verification timed out.");
            return;
        }

        statusCheckHandler.postDelayed(() -> {
            checkPaymentStatus(transactionId, new OnCompleteListener<PaymentStatusResponse>() {
                @Override
                public void onSuccess(PaymentStatusResponse result) {
                    if (result.getTransaction().isSuccess()) {
                        // Update Firebase wallet
                        FirebaseHelper.addToDoctorWallet(doctorId, amount, callback);
                    } else if (result.getTransaction().isFailed()) {
                        isProcessingPayment = false;
                        callback.onError("Payment was unsuccessful.");
                    } else {
                        // Continue polling
                        pollPaymentStatus(transactionId, doctorId, amount, attemptCount + 1, callback);
                    }
                }

                @Override
                public void onError(String error) {
                    // Continue polling on error
                    pollPaymentStatus(transactionId, doctorId, amount, attemptCount + 1, callback);
                }
            });
        }, STATUS_CHECK_INTERVAL);
    }
}
```

---

## Testing

### Test Payment Initiation (Local)
```bash
curl -X POST http://127.0.0.1:8001/api/payment/initiate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "user_id": "test_user_123",
    "doctor_id": "test_doc_456",
    "amount": 25000,
    "provider": "Mpesa",
    "payment_account": "255712345678"
  }'
```

### Test Payment Status
```bash
curl "http://127.0.0.1:8001/api/payment/status?transaction_id=1"
```

### View All Transactions
```bash
php artisan tinker --execute="echo App\Models\Transaction::all()->toJson(JSON_PRETTY_PRINT);"
```

### View Recent Logs
```bash
tail -f storage/logs/laravel.log | grep -i sonicpesa
```

---

## Error Codes

| HTTP Code | Description |
|-----------|-------------|
| 200 | Success |
| 400 | Bad Request |
| 404 | Transaction not found |
| 422 | Validation Error |
| 429 | Too Many Requests (duplicate payment) |
| 500 | Internal Server Error |

---

## Security Notes

1. **API Authentication**: In production, implement Laravel Sanctum token authentication
2. **HTTPS Only**: Always use HTTPS in production
3. **Webhook Verification**: Verify SonicPesa signature using X-SonicPesa-Signature header
4. **Rate Limiting**: Configure API rate limiting in `.env`
5. **Input Validation**: All inputs are validated server-side

---

## Troubleshooting

### USSD Not Showing on Phone
- Verify phone number format is correct (255XXXXXXXXX, no + or leading 0)
- Check SONICPESA credentials in `.env`
- Check Laravel logs for SonicPesa API errors

### Status Stuck on "pending"
- Check if webhook is configured in SonicPesa dashboard
- Verify `external_reference` is being saved in database
- Check network connectivity to SonicPesa API

### Too Many Requests
- This is intentional - 2-minute de-bounce prevents duplicate USSD prompts
- Wait for existing transaction to complete or cancel it

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-23 | Initial documentation |
| 1.1.0 | 2026-02-23 | Added SonicPesa v1 API integration, status check endpoint |

**Last Updated:** 2026-02-23
