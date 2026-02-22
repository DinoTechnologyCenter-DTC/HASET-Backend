# Using ngrok with HASET Payment Backend

## What is ngrok?

ngrok creates a secure tunnel from a public URL to your local Laravel server, allowing:
- Your Android app to access the backend from anywhere
- ClickPesa to send webhooks to your local machine
- HTTPS support for secure connections

---

## Installation

### 1. Install ngrok

```bash
# Download and install ngrok
curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.asc | \
  sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null && \
  echo "deb https://ngrok-agent.s3.amazonaws.com buster main" | \
  sudo tee /etc/apt/sources.list.d/ngrok.list && \
  sudo apt update && sudo apt install ngrok
```

Or download from: https://ngrok.com/download

### 2. Sign Up (Optional but Recommended)

1. Go to https://dashboard.ngrok.com/signup
2. Get your auth token
3. Configure it:

```bash
ngrok config add-authtoken YOUR_AUTH_TOKEN
```

---

## Running ngrok

### Start Your Laravel Server

```bash
cd /home/mrdinotz/AndroidStudioProjects/haset-backend/HASET-Backend
php artisan serve --port=8001
```

### Start ngrok in a New Terminal

```bash
ngrok http 8001
```

You'll see output like:

```
ngrok                                                                    

Session Status                online
Account                       Your Name (Plan: Free)
Version                       3.x.x
Region                        United States (us)
Latency                       -
Web Interface                 http://127.0.0.1:4040
Forwarding                    https://abc123.ngrok-free.app -> http://localhost:8001

Connections                   ttl     opn     rt1     rt5     p50     p90
                              0       0       0.00    0.00    0.00    0.00
```

**Your public URL is:** `https://abc123.ngrok-free.app`

---

## Configure Your Backend

### Update .env (Optional)

```bash
APP_URL=https://your-ngrok-url.ngrok-free.app
```

### Update CORS (if needed)

Laravel should handle this automatically, but if you get CORS errors, update `config/cors.php`:

```php
'allowed_origins' => ['*'], // For development only
```

---

## Configure Your Android App

### Update RetrofitClient.java

```java
public class RetrofitClient {
    // Replace with your ngrok URL
    private static final String BASE_URL = "https://abc123.ngrok-free.app/api/";
    
    // ... rest of the code
}
```

**Important:** Update this URL every time you restart ngrok (free tier gives random URLs).

---

## Configure ClickPesa Webhook

### 1. Login to ClickPesa Dashboard

Go to: https://dashboard.clickpesa.com

### 2. Set Webhook URL

Navigate to: **Settings → Webhooks**

Set your webhook URL to:
```
https://your-ngrok-url.ngrok-free.app/api/payment/callback
```

### 3. Enable Payment Methods

Go to: **Settings → Payment Methods**

Enable the providers you want to support:
- M-Pesa
- Airtel Money
- Tigo Pesa
- Halopesa
- etc.

### 4. Enable ClickPesa in .env

Once configured, enable ClickPesa:

```bash
CLICKPESA_ENABLED=true
```

---

## Testing the Setup

### 1. Test Backend Accessibility

From your mobile device or another computer:

```bash
curl https://your-ngrok-url.ngrok-free.app/api/payment/initiate \
  -H "Content-Type: application/json" \
  -d '{"doctor_id":"test","amount":1000,"provider":"Mpesa","payment_account":"+255712345678"}'
```

### 2. Monitor Requests

ngrok provides a web interface to monitor all requests:

Open in browser: http://127.0.0.1:4040

This shows:
- All incoming requests
- Request/response details
- Webhook callbacks from ClickPesa

### 3. Test from Android App

1. Update `BASE_URL` in your Android app
2. Build and run the app
3. Try making a payment
4. Monitor the ngrok web interface to see the requests

---

## Important Notes

### Free Tier Limitations

- **Random URLs**: Each time you restart ngrok, you get a new URL
- **Session Timeout**: Free sessions expire after 2 hours
- **Update Required**: You'll need to update your Android app's `BASE_URL` each time

### Paid Tier Benefits ($8/month)

- **Fixed URL**: Get a permanent subdomain (e.g., `haset.ngrok.app`)
- **No Timeout**: Sessions don't expire
- **Multiple Tunnels**: Run multiple services simultaneously

### Security Considerations

⚠️ **For Development Only**

- ngrok exposes your local server to the internet
- Don't use in production
- Don't commit ngrok URLs to git
- Monitor the web interface for suspicious activity

---

## Troubleshooting

### ngrok Not Found

```bash
# Check if installed
which ngrok

# If not found, install again or use direct binary
./ngrok http 8001
```

### Connection Refused

Make sure Laravel is running:
```bash
php artisan serve --port=8001
```

### CORS Errors

Add to `.env`:
```bash
SANCTUM_STATEFUL_DOMAINS=*.ngrok-free.app
SESSION_DOMAIN=.ngrok-free.app
```

### Webhook Not Received

1. Check ngrok web interface (http://127.0.0.1:4040)
2. Verify webhook URL in ClickPesa dashboard
3. Check Laravel logs: `tail -f storage/logs/laravel.log`

---

## Production Deployment

When ready for production:

1. **Deploy to a Server**
   - DigitalOcean, AWS, Heroku, etc.
   - Get a real domain name
   - Set up SSL certificate

2. **Update Android App**
   - Change `BASE_URL` to production URL
   - Build release APK

3. **Update ClickPesa**
   - Set production webhook URL
   - Switch from sandbox to live credentials

---

## Quick Reference

### Start Everything

```bash
# Terminal 1: Laravel
cd /home/mrdinotz/AndroidStudioProjects/haset-backend/HASET-Backend
php artisan serve --port=8001

# Terminal 2: ngrok
ngrok http 8001

# Terminal 3: Monitor logs
tail -f storage/logs/laravel.log
```

### Your URLs

- **Backend (local)**: http://127.0.0.1:8001
- **Backend (public)**: https://your-url.ngrok-free.app
- **ngrok Dashboard**: http://127.0.0.1:4040
- **API Endpoint**: https://your-url.ngrok-free.app/api/payment/initiate
- **Webhook**: https://your-url.ngrok-free.app/api/payment/callback

---

## Next Steps

1. ✅ Install ngrok
2. ✅ Start Laravel server
3. ✅ Start ngrok tunnel
4. ✅ Copy ngrok URL
5. ✅ Update Android app's `BASE_URL`
6. ✅ Configure ClickPesa webhook
7. ✅ Enable payment methods in ClickPesa
8. ✅ Set `CLICKPESA_ENABLED=true`
9. ✅ Test payment flow
10. ✅ Monitor ngrok dashboard

---

**Ready to test!** 🚀
