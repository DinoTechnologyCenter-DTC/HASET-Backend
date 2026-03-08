# 🚀 SonicPesa Payment Integration Guide

This document provides a comprehensive guide to the **SonicPesa** payment integration in the HASET ecosystem. It covers both the Laravel backend and the Android mobile application.

---

## 🏗️ System Architecture

The payment system uses an asynchronous **USSD Push** (M-Pesa, Tigo Pesa, Airtel Money, HaloPesa) model:

1.  **Android App**: Patient enters their mobile money number and initiates payment.
2.  **Laravel Backend**: Validates requests, tracks transactions in SQLite, and communicates with SonicPesa API.
3.  **SonicPesa Gateway**: Aggregator that triggers the USSD prompt on the user's phone.
4.  **Mobile Provider**: Processes the PIN entry and confirms the funds transfer.

---

## 🛠️ API Endpoints

### 1. Initiate Payment
**POST** `/api/payment/initiate`
- Used by patients to pay for consultations.
- **De-bounce**: Blocks duplicate requests within 2 minutes for the same user/doctor.

### 2. Check Status
**GET** `/api/payment/status?transaction_id={id}`
- Polled by the Android app every 6 seconds to detect when a payment is completed.

### 3. Disburse Funds (Payout)
**POST** `/api/payment/payout`
- Used by Admins to approve doctor withdrawals.
- **Security**: Requires an Admin Password (configured in `.env`).

### 4. Callback (Webhook)
**POST** `/api/payment/callback`
- Endpoint for SonicPesa to notify the backend of successful transactions.

---

## ⚙️ Configuration (.env)

The following variables must be configured in your backend `.env` file:

```env
# SonicPesa Gateway
SONICPESA_ENABLED=true
SONICPESA_API_KEY=sk_live_xxxxxxxxxxxxxxxxxxxx
SONICPESA_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
SONICPESA_BASE_URL=https://api.sonicpesa.com/api/v1

# Admin Security
ADMIN_PAYOUT_PASSWORD=admin123
```

---

## 📲 Android Integration Details

### Base URL
Ensure the `DEVELOPMENT_API_URL` or `PRODUCTION_API_URL` in `Constants.java` matches your backend environment.

### Payment Flow
1. Patient initiates payment → App calls `/payment/initiate`.
2. App shows "Check your phone" overlay.
3. App polls `/payment/status` until `success` or `failed`.
4. On success, `PaymentRepository` automatically updates the Firebase doctor wallet.

### Withdrawal Flow
1. Admin clicks "Approve" in Wallet Management.
2. App prompts for **Admin Password**.
3. App calls `/payment/payout`.
4. If backend returns success, App updates Firebase withdrawal status.

---

## 🧪 Testing in Development

### 1. Start Laravel
```bash
php artisan serve --port=8000
```

### 2. Start ngrok (for physical device and webhooks)
```bash
ngrok http 8000
```

### 3. Monitor Logs
```bash
# Backend logs
tail -f storage/logs/laravel.log

# ngrok traffic
http://127.0.0.1:4040
```

---

## 🚩 Payment Limits
- **Minimum**: 500 TZS
- **Maximum**: 5,000,000 TZS
- *These are configurable in `PaymentController.php`.*

---

---

## 🔍 Troubleshooting Payouts (404 Error)

If you encounter a **404 Not Found** error during admin withdrawals:

1.  **Endpoint Verification**: SonicPesa's B2C/Payout API may use a different path or require specific account permissions.
2.  **Simulation Mode**: For testing the accounting flow (Firebase updates), set `SONICPESA_ENABLED=false` in `.env`.
3.  **Support**: Contact SonicPesa support to confirm the correct URL for disbursement/payouts for your account type.

**Current URL used by Backend**: `https://api.sonicpesa.com/api/v1/payment/payout`

---

**Last Updated**: 2026-03-09
**Status**: SonicPesa Integration ✅ Active | Payout Endpoint ⚠️ Pending Verification
