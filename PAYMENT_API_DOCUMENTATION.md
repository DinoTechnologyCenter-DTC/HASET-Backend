# HASET Payment Backend API Documentation

## Overview
This Laravel backend service handles payment processing for the HASET mobile application. It provides endpoints for initiating payments and receiving payment gateway callbacks.

**Base URL (Development):** `http://127.0.0.1:8001/api`

---

## Endpoints

### 1. Initiate Payment

**Endpoint:** `POST /payment/initiate`

**Description:** Creates a payment transaction record and initiates payment processing.

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
  "payment_account": "+255712345678"
}
```

**Request Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `user_id` | string | No | Firebase UID of the patient making payment |
| `doctor_id` | string | Yes | Firebase UID or ID of the doctor receiving payment |
| `amount` | number | Yes | Payment amount in TZS (minimum: 1) |
| `provider` | string | Yes | Payment provider (e.g., "Mpesa", "Airtel Money", "CRDB", "NMB") |
| `payment_account` | string | Yes | Mobile number (for mobile money) or account number (for bank) |

**Success Response (200 OK):**
```json
{
  "status": "success",
  "message": "Payment initiated successfully",
  "transaction_id": 1
}
```

**Error Response (422 Unprocessable Entity):**
```json
{
  "message": "The amount field is required.",
  "errors": {
    "amount": [
      "The amount field is required."
    ]
  }
}
```

**Example cURL:**
```bash
curl -X POST http://127.0.0.1:8001/api/payment/initiate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "user_id": "firebase_user_123",
    "doctor_id": "doc_456",
    "amount": 15000,
    "provider": "Mpesa",
    "payment_account": "+255712345678"
  }'
```

---

### 2. Payment Callback (Webhook)

**Endpoint:** `POST /payment/callback`

**Description:** Receives webhook notifications from payment gateways about transaction status updates.

**Headers:**
```
Content-Type: application/json
```

**Request Body:** (Varies by payment gateway)

**Success Response (200 OK):**
```json
{
  "status": "received"
}
```

---

## Database Schema

### Transactions Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | string (nullable) | Firebase UID of the patient |
| `doctor_id` | string (nullable) | Firebase UID of the doctor |
| `amount` | decimal(12,2) | Payment amount |
| `currency` | string | Currency code (default: "TZS") |
| `provider` | string (nullable) | Payment provider name |
| `payment_account` | string (nullable) | Phone number or account number |
| `status` | string | Transaction status: "pending", "success", "failed" |
| `external_reference` | string (nullable) | Payment gateway transaction ID |
| `description` | string (nullable) | Transaction description |
| `created_at` | timestamp | Record creation time |
| `updated_at` | timestamp | Last update time |

---

## Android Integration

### Step 1: Add Retrofit Dependencies

Add to your `build.gradle` (app level):

```gradle
dependencies {
    implementation 'com.squareup.retrofit2:retrofit:2.9.0'
    implementation 'com.squareup.retrofit2:converter-gson:2.9.0'
    implementation 'com.squareup.okhttp3:logging-interceptor:4.11.0'
}
```

### Step 2: Create API Interface

Create `PaymentApiService.java`:

```java
package com.haset.hasetapp.api;

import com.haset.hasetapp.models.PaymentRequest;
import com.haset.hasetapp.models.PaymentResponse;

import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.POST;

public interface PaymentApiService {
    @POST("payment/initiate")
    Call<PaymentResponse> initiatePayment(@Body PaymentRequest request);
}
```

### Step 3: Create Data Models

Create `PaymentRequest.java`:

```java
package com.haset.hasetapp.models;

public class PaymentRequest {
    private String user_id;
    private String doctor_id;
    private double amount;
    private String provider;
    private String payment_account;

    public PaymentRequest(String userId, String doctorId, double amount, 
                         String provider, String paymentAccount) {
        this.user_id = userId;
        this.doctor_id = doctorId;
        this.amount = amount;
        this.provider = provider;
        this.payment_account = paymentAccount;
    }

    // Getters and setters
}
```

Create `PaymentResponse.java`:

```java
package com.haset.hasetapp.models;

public class PaymentResponse {
    private String status;
    private String message;
    private int transaction_id;

    // Getters and setters
    public String getStatus() { return status; }
    public String getMessage() { return message; }
    public int getTransactionId() { return transaction_id; }
}
```

### Step 4: Create Retrofit Client

Create `RetrofitClient.java`:

```java
package com.haset.hasetapp.api;

import okhttp3.OkHttpClient;
import okhttp3.logging.HttpLoggingInterceptor;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;

public class RetrofitClient {
    private static final String BASE_URL = "http://10.0.2.2:8001/api/"; // For emulator
    // Use "http://YOUR_LOCAL_IP:8001/api/" for physical device
    
    private static Retrofit retrofit = null;

    public static Retrofit getClient() {
        if (retrofit == null) {
            HttpLoggingInterceptor interceptor = new HttpLoggingInterceptor();
            interceptor.setLevel(HttpLoggingInterceptor.Level.BODY);
            
            OkHttpClient client = new OkHttpClient.Builder()
                    .addInterceptor(interceptor)
                    .build();

            retrofit = new Retrofit.Builder()
                    .baseUrl(BASE_URL)
                    .client(client)
                    .addConverterFactory(GsonConverterFactory.create())
                    .build();
        }
        return retrofit;
    }
}
```

### Step 5: Update PaymentRepository

Modify `PaymentRepository.java`:

```java
package com.haset.hasetapp.repositories;

import android.util.Log;

import com.haset.hasetapp.api.PaymentApiService;
import com.haset.hasetapp.api.RetrofitClient;
import com.haset.hasetapp.models.PaymentRequest;
import com.haset.hasetapp.models.PaymentResponse;
import com.haset.hasetapp.utils.FirebaseHelper;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class PaymentRepository {
    private static final String TAG = "PaymentRepository";
    private PaymentApiService apiService;

    public PaymentRepository() {
        apiService = RetrofitClient.getClient().create(PaymentApiService.class);
    }

    public void processPayment(String userId, String doctorId, double amount, 
                              String provider, String paymentAccount,
                              FirebaseHelper.OnCompleteListener<Boolean> callback) {
        
        PaymentRequest request = new PaymentRequest(userId, doctorId, amount, 
                                                    provider, paymentAccount);
        
        Call<PaymentResponse> call = apiService.initiatePayment(request);
        
        call.enqueue(new Callback<PaymentResponse>() {
            @Override
            public void onResponse(Call<PaymentResponse> call, Response<PaymentResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    PaymentResponse paymentResponse = response.body();
                    Log.d(TAG, "Payment initiated: " + paymentResponse.getMessage());
                    Log.d(TAG, "Transaction ID: " + paymentResponse.getTransactionId());
                    
                    // Also update Firebase wallet
                    FirebaseHelper.addToDoctorWallet(doctorId, amount, callback);
                } else {
                    Log.e(TAG, "Payment failed: " + response.code());
                    callback.onComplete(false, new Exception("Payment initiation failed"));
                }
            }

            @Override
            public void onFailure(Call<PaymentResponse> call, Throwable t) {
                Log.e(TAG, "Network error: " + t.getMessage());
                callback.onComplete(false, new Exception(t.getMessage()));
            }
        });
    }
    
    // Keep the old method for backward compatibility
    public void addToDoctorWallet(String doctorId, double amount, 
                                 FirebaseHelper.OnCompleteListener<Boolean> callback) {
        FirebaseHelper.addToDoctorWallet(doctorId, amount, callback);
    }
}
```

### Step 6: Update PaymentActivity

In your `processPayment()` method in `PaymentActivity.java`, update the call:

```java
private void processPayment() {
    // ... existing code ...
    
    handler.postDelayed(() -> {
        progressIndicator.setProgress(100, true);
        
        if (doctor != null) {
            String doctorId = doctor.getDoctorId() != null ? 
                            doctor.getDoctorId() : doctor.getUserId();
            String userId = getCurrentUserId(); // Get from Firebase Auth
            
            if (doctorId != null) {
                // Call the new method with payment details
                viewModel.processPayment(userId, doctorId, consultationFee, 
                                       paymentProvider, walletNumber);
            } else {
                Toast.makeText(this, "Error: Doctor ID missing", 
                             Toast.LENGTH_SHORT).show();
                // ... error handling ...
            }
        }
    }, 2000);
}

private String getCurrentUserId() {
    return com.google.firebase.auth.FirebaseAuth.getInstance()
           .getCurrentUser() != null ? 
           com.google.firebase.auth.FirebaseAuth.getInstance()
           .getCurrentUser().getUid() : null;
}
```

### Step 7: Update PaymentViewModel

Update `PaymentViewModel.java`:

```java
public void processPayment(String userId, String doctorId, double amount,
                          String provider, String paymentAccount) {
    processing.setValue(true);
    
    repository.processPayment(userId, doctorId, amount, provider, paymentAccount,
        new FirebaseHelper.OnCompleteListener<Boolean>() {
            @Override
            public void onComplete(Boolean result, Exception exception) {
                processing.setValue(false);
                if (exception == null && result) {
                    success.setValue(true);
                } else {
                    error.setValue(exception != null ? 
                                 exception.getMessage() : "Payment failed");
                }
            }
        });
}
```

### Step 8: Add Internet Permission

In `AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.INTERNET" />
```

### Step 9: Allow Cleartext Traffic (Development Only)

In `AndroidManifest.xml`, add to `<application>` tag:

```xml
android:usesCleartextTraffic="true"
```

**⚠️ Important:** Remove this in production and use HTTPS!

---

## Testing

### Test Payment Initiation

```bash
curl -X POST http://127.0.0.1:8001/api/payment/initiate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "user_id": "test_user_123",
    "doctor_id": "test_doc_456",
    "amount": 25000,
    "provider": "Mpesa",
    "payment_account": "+255712345678"
  }'
```

### View All Transactions

```bash
php artisan tinker --execute="echo json_encode(App\Models\Transaction::all()->toArray(), JSON_PRETTY_PRINT);"
```

---

## Next Steps (Production Readiness)

1. **Payment Gateway Integration:**
   - Integrate with actual payment providers (Selcom, AzamPay, Chapa, etc.)
   - Implement proper webhook handling
   - Add transaction verification

2. **Security:**
   - Add API authentication (Laravel Sanctum tokens)
   - Implement rate limiting
   - Add request signing/verification
   - Use HTTPS in production

3. **Database:**
   - Switch from SQLite to MySQL/PostgreSQL
   - Add database indexes for performance
   - Implement transaction logging

4. **Error Handling:**
   - Add comprehensive error responses
   - Implement retry mechanisms
   - Add transaction rollback logic

5. **Monitoring:**
   - Add logging (Laravel Log)
   - Implement transaction status tracking
   - Set up alerts for failed payments

6. **Deployment:**
   - Deploy to production server
   - Configure environment variables
   - Set up SSL certificate
   - Configure CORS for mobile app

---

## Support

For issues or questions, contact the development team.

**Version:** 1.0.0  
**Last Updated:** 2026-01-23
