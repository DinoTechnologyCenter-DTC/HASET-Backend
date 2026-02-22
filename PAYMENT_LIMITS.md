# HASET Payment System - Limits & Restrictions

## 💰 Payment Amount Limits

### Backend Validation (Your System)
**Current Settings:**
- **Minimum:** 50 TZS (~$0.02)
- **Maximum:** 5,000,000 TZS (~$2,000)

These limits are enforced in `PaymentController.php` and will return an error if exceeded.

**Why these limits?**
- **Minimum (50 TZS):** Prevents spam/test transactions and covers transaction fees
- **Maximum (5M TZS):** Safe limit that works across all mobile money providers

**To change these limits:**
Edit `/app/Http/Controllers/Api/PaymentController.php`:
```php
'amount' => 'required|numeric|min:50|max:5000000',
//                              ^^^        ^^^^^^^
//                              Min        Max
```

---

### Mobile Money Provider Limits

#### 🟢 M-Pesa (Vodacom) - RECOMMENDED
- **Single Transaction:** Up to **10,000,000 TZS** (~$4,000)
- **Daily Limit:** Varies (typically 10M - 20M TZS)
- **Monthly Limit:** Varies by account
- **Best for:** Large transactions

#### 🟡 Airtel Money
- **Single Transaction:** Up to **5,000,000 TZS** (~$2,000)
- **Daily Limit:** Varies (typically 5M - 10M TZS)
- **Monthly Limit:** Varies by account
- **Best for:** Medium transactions

#### 🟡 Tigo Pesa
- **Single Transaction:** Up to **5,000,000 TZS** (~$2,000)
- **Daily Limit:** Varies (typically 5M - 10M TZS)
- **Monthly Limit:** Varies by account
- **Best for:** Medium transactions

#### 🔴 Halopesa
- **Single Transaction:** Up to **3,000,000 TZS** (~$1,200)
- **Daily Limit:** Varies (typically 3M - 5M TZS)
- **Monthly Limit:** Varies by account
- **Best for:** Small to medium transactions

**Note:** Actual limits depend on:
- User's KYC verification level
- Account type (basic vs verified)
- Transaction history
- Provider's current policies

---

### ClickPesa Limits

#### Account-Based Limits
Your limits depend on your ClickPesa account verification:

**Sandbox/Test Mode:**
- Usually limited to small test amounts
- May have transaction count limits
- Not for real money

**Live Mode - Unverified:**
- Lower transaction limits
- May require business verification for higher amounts

**Live Mode - Verified Business:**
- Higher transaction limits
- Higher daily/monthly volumes
- Better rates

**Check your limits:**
1. Login to https://dashboard.clickpesa.com
2. Go to Settings → Account Limits
3. View your current limits

---

## 🔢 Transaction Limits

### Rate Limiting (API Calls)

**Current Settings:** None (unlimited)

**Recommended for Production:**
Add rate limiting to prevent abuse:

```php
// In routes/api.php
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/payment/initiate', [PaymentController::class, 'initiate']);
});
```

This limits to **60 requests per minute** per IP address.

---

### Concurrent Transactions

**Current:** No limit on concurrent transactions

**Recommendation:**
- Monitor for duplicate transactions
- Implement idempotency (same request = same result)
- Add transaction locking if needed

---

## 📊 Recommended Limits by Use Case

### For Medical Consultations (Your App)

**Typical Consultation Fees in Tanzania:**
- General Consultation: 10,000 - 50,000 TZS
- Specialist Consultation: 50,000 - 150,000 TZS
- Emergency Consultation: 100,000 - 300,000 TZS

**Recommended Backend Limits:**
```php
'amount' => 'required|numeric|min:5000|max:500000',
// Min: 5,000 TZS (~$2) - Covers basic consultation
// Max: 500,000 TZS (~$200) - Covers specialist consultation
```

**For higher amounts (e.g., procedures):**
```php
'amount' => 'required|numeric|min:5000|max:2000000',
// Max: 2,000,000 TZS (~$800) - Covers minor procedures
```

---

## ⚠️ Important Considerations

### 1. Transaction Fees
Mobile money providers charge fees:
- **M-Pesa:** ~1-3% of transaction amount
- **Airtel Money:** ~1-3% of transaction amount
- **Tigo Pesa:** ~1-3% of transaction amount

**Who pays?**
- Usually the sender (patient)
- ClickPesa may also charge merchant fees

### 2. User Account Limits
Even if your system allows 5M TZS, the user's account might have lower limits:
- Unverified accounts: Lower limits
- Verified accounts: Higher limits
- Business accounts: Highest limits

**Solution:** Show clear error messages when limits are exceeded.

### 3. Daily/Monthly Limits
Users may hit their daily/monthly limits:
- Daily limit reached → Payment fails
- Monthly limit reached → Payment fails

**Solution:** Inform users to try again tomorrow or verify their account.

---

## 🛡️ Security Limits

### Fraud Prevention

**Recommended Measures:**

1. **Maximum Transactions per User per Day:**
```php
// In PaymentController.php
$todayTransactions = Transaction::where('user_id', $userId)
    ->whereDate('created_at', today())
    ->count();

if ($todayTransactions >= 10) {
    return response()->json([
        'status' => 'error',
        'message' => 'Daily transaction limit reached. Please try again tomorrow.'
    ], 429);
}
```

2. **Maximum Amount per User per Day:**
```php
$todayAmount = Transaction::where('user_id', $userId)
    ->whereDate('created_at', today())
    ->sum('amount');

if ($todayAmount >= 1000000) { // 1M TZS per day
    return response()->json([
        'status' => 'error',
        'message' => 'Daily amount limit reached.'
    ], 429);
}
```

3. **Duplicate Transaction Prevention:**
```php
// Check for duplicate within last 5 minutes
$recentTransaction = Transaction::where('user_id', $userId)
    ->where('doctor_id', $doctorId)
    ->where('amount', $amount)
    ->where('created_at', '>', now()->subMinutes(5))
    ->first();

if ($recentTransaction) {
    return response()->json([
        'status' => 'error',
        'message' => 'Duplicate transaction detected. Please wait before trying again.'
    ], 429);
}
```

---

## 📋 Current Implementation Summary

### ✅ What's Implemented
- ✅ Minimum amount: 500 TZS
- ✅ Maximum amount: 5,000,000 TZS
- ✅ Input validation
- ✅ Error messages

### ⏳ Not Yet Implemented (Optional)
- ⏳ Rate limiting (API calls per minute)
- ⏳ Daily transaction count limit per user
- ⏳ Daily amount limit per user
- ⏳ Duplicate transaction prevention
- ⏳ Fraud detection

---

## 🎯 Recommendations

### For Development/Testing
**Current limits are fine:**
- Min: 500 TZS
- Max: 5,000,000 TZS

### For Production

**Option 1: Conservative (Recommended for Launch)**
```php
'amount' => 'required|numeric|min:5000|max:500000',
// Min: 5,000 TZS - Reasonable consultation fee
// Max: 500,000 TZS - Covers most consultations
```

**Option 2: Moderate**
```php
'amount' => 'required|numeric|min:5000|max:2000000',
// Min: 5,000 TZS
// Max: 2,000,000 TZS - Covers procedures
```

**Option 3: Liberal (Current)**
```php
'amount' => 'required|numeric|min:500|max:5000000',
// Min: 500 TZS
// Max: 5,000,000 TZS - Maximum flexibility
```

---

## 🔧 How to Change Limits

### Backend (Laravel)
Edit: `/app/Http/Controllers/Api/PaymentController.php`
```php
'amount' => 'required|numeric|min:YOUR_MIN|max:YOUR_MAX',
```

### Android App (Optional)
Add client-side validation in `PaymentActivity.java`:
```java
if (consultationFee < 5000) {
    Toast.makeText(this, "Minimum payment is 5,000 TZS", Toast.LENGTH_SHORT).show();
    return;
}

if (consultationFee > 500000) {
    Toast.makeText(this, "Maximum payment is 500,000 TZS", Toast.LENGTH_SHORT).show();
    return;
}
```

---

## 📞 Getting Higher Limits

### ClickPesa
1. Login to dashboard
2. Complete business verification
3. Submit required documents
4. Request limit increase

### Mobile Money Providers
Users need to:
1. Verify their identity (KYC)
2. Upgrade account type
3. Contact provider for business accounts

---

## Summary

**Your current limits:**
- ✅ **Min:** 500 TZS (~$0.20)
- ✅ **Max:** 5,000,000 TZS (~$2,000)

**These limits are:**
- ✅ Safe for testing
- ✅ Reasonable for production
- ✅ Within mobile money provider limits
- ✅ Flexible enough for most consultations

**You can adjust them anytime** based on your business needs!

---

**Last Updated:** 2026-01-23  
**Version:** 1.0.0
