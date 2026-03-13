# HASET Payment Backend API Documentation

## Overview
The HASET Payment Backend is a Laravel-based REST API for processing mobile money payments in Tanzania using the Zeno payment gateway. It supports payment initiation, status checking, payouts, and webhook callbacks.

**Base URL:** `https://payments.hasethospital.or.tz`

---

## API Endpoints

### 1. Initiate Payment
**Endpoint:** `POST /api/payment/initiate`

**Request:**
```json
{
  "user_id": "string (optional)",
  "doctor_id": "string (required)",
  "amount": "number (required, min: 50, max: 5000000)",
  "provider": "string (required)",
  "payment_account": "string (required, phone number)"
}
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Payment initiated successfully.",
  "transaction_id": 15,
  "order_reference": "HASET15T1773141947"
}
```

**Example:**
```bash
curl -X POST https://payments.hasethospital.or.tz/api/payment/initiate \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "user_123",
    "doctor_id": "doc_001",
    "amount": 1000,
    "provider": "Vodacom",
    "payment_account": "0683859574"
  }'
```

---

### 2. Check Payment Status
**Endpoint:** `GET /api/payment/status?transaction_id=15`

**Success Response (200):**
```json
{
  "status": "success",
  "transaction": {
    "id": 15,
    "status": "processing",
    "amount": "1000.00",
    "currency": "TZS",
    "provider": "Vodacom"
  }
}
```

**Status Values:** `pending`, `processing`, `success`, `failed`

---

### 3. Cancel Payment
**Endpoint:** `POST /api/payment/cancel`

**Request:**
```json
{
  "transaction_id": 15
}
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Transaction cancelled"
}
```

---

### 4. Get Account Balance
**Endpoint:** `GET /api/payment/balance`

**Success Response (200):**
```json
{
  "status": "success",
  "data": {
    "balance": 4550200.0,
    "currency": "TZS"
  }
}
```

---

### 5. Process Payout
**Endpoint:** `POST /api/payment/payout`

**Request:**
```json
{
  "request_id": "string",
  "doctor_id": "string",
  "amount": "number",
  "phone_number": "string",
  "provider": "string",
  "admin_id": "string",
  "password": "string"
}
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Funds successfully disbursed",
  "transaction_id": 16
}
```

---

### 6. Payment Callback (Webhook)
**Endpoint:** `POST /api/payment/callback`

**Webhook URL to configure in Zeno:** `https://payments.hasethospital.or.tz/api/payment/callback`

---

## Configuration

**Environment Variables (.env):**
```
ZENO_ENABLED=true
ZENO_API_KEY=_z0jpBDEquS6HoFuwzqP6svHXsX3b4hXwSxFTZbTdX1unfLmWfna5tDV5HoXKvp3y80SbPYG0PykrdRU73HXHA
ZENO_BASE_URL=https://zeno.co.tz
```

---

## Deployment

**Server:** Hostinger
**Domain:** `payments.hasethospital.or.tz`
**Document Root:** `/home/u232077031/domains/hasethospital.or.tz/public_html/paymnt/public`
**Laravel Root:** `/home/u232077031/domains/hasethospital.or.tz/public_html/paymnt`

---

## Support

**Zeno Support:** support@zenoapi.com
**Logs:** `storage/logs/laravel.log`
