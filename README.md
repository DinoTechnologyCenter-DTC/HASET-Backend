# HASET Payment Backend

A Laravel-based payment processing backend for the HASET mobile health application. This service handles payment transactions for doctor consultations using various Tanzanian payment providers.

## 🚀 Features

- **Payment Initiation**: Create and track payment transactions
- **Multiple Payment Providers**: Support for:
  - Mobile Money (M-Pesa, Airtel Money, Tigo Pesa, Halopesa, Mixx by Yas)
  - Bank Cards (CRDB, NMB, TCB, AKIBA)
- **Transaction Tracking**: Complete audit trail of all payments
- **RESTful API**: Easy integration with mobile applications
- **Webhook Support**: Handle payment gateway callbacks
- **Database Logging**: All transactions stored for reconciliation

## 📋 Requirements

- PHP 8.3 or higher
- Composer 2.9+
- SQLite (development) or MySQL/PostgreSQL (production)
- Laravel 12.x

## 🛠️ Installation

### 1. Clone the Repository

```bash
cd /home/mrdinotz/AndroidStudioProjects/haset-backend/HASET-Backend
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure your database and payment gateway credentials.

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Start Development Server

```bash
php artisan serve --port=8001
```

The API will be available at `http://127.0.0.1:8001/api`

## 📚 API Documentation

See [PAYMENT_API_DOCUMENTATION.md](./PAYMENT_API_DOCUMENTATION.md) for complete API documentation including:
- Endpoint specifications
- Request/response examples
- Android integration guide
- Testing instructions

## 🔧 Quick Start

### Test the API

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

### View Transactions

```bash
php artisan tinker --execute="echo json_encode(App\Models\Transaction::all()->toArray(), JSON_PRETTY_PRINT);"
```

## 📁 Project Structure

```
HASET-Backend/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── PaymentController.php    # Payment endpoints
│   └── Models/
│       └── Transaction.php                  # Transaction model
├── database/
│   └── migrations/
│       └── *_create_transactions_table.php  # Database schema
├── routes/
│   └── api.php                              # API routes
├── .env.example                             # Environment template
├── PAYMENT_API_DOCUMENTATION.md             # API docs
└── README.md                                # This file
```

## 🗄️ Database Schema

### Transactions Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | string | Patient's Firebase UID |
| doctor_id | string | Doctor's Firebase UID |
| amount | decimal(12,2) | Payment amount in TZS |
| currency | string | Currency code (default: TZS) |
| provider | string | Payment provider name |
| payment_account | string | Phone/account number |
| status | string | pending/success/failed |
| external_reference | string | Gateway transaction ID |
| description | string | Transaction description |
| created_at | timestamp | Creation time |
| updated_at | timestamp | Last update time |

## 🔐 Security Considerations

### Development
- Currently uses SQLite for easy setup
- No authentication required (for testing)
- Cleartext HTTP allowed

### Production (TODO)
- [ ] Switch to MySQL/PostgreSQL
- [ ] Implement Laravel Sanctum authentication
- [ ] Add API rate limiting
- [ ] Enable HTTPS only
- [ ] Add request signing/verification
- [ ] Implement CORS properly
- [ ] Add IP whitelisting
- [ ] Enable audit logging

## 🧪 Testing

### Run Tests
```bash
php artisan test
```

### Manual Testing
See the API documentation for curl examples.

## 🚀 Deployment

### Production Checklist

1. **Environment**
   - Set `APP_ENV=production`
   - Set `APP_DEBUG=false`
   - Generate new `APP_KEY`
   - Configure production database

2. **Database**
   - Use MySQL or PostgreSQL
   - Run migrations: `php artisan migrate --force`
   - Set up backups

3. **Security**
   - Configure CORS
   - Enable rate limiting
   - Set up SSL certificate
   - Configure firewall rules

4. **Payment Gateways**
   - Add production API keys
   - Configure webhook URLs
   - Test with sandbox first

5. **Monitoring**
   - Set up error logging
   - Configure alerts
   - Monitor transaction status

## 🔌 Payment Gateway Integration

This backend is prepared for integration with:

- **Selcom**: Tanzania's leading payment gateway
- **AzamPay**: Mobile and card payments
- **Chapa**: Pan-African payment solution

Add your credentials in `.env` and implement the gateway logic in `PaymentController.php`.

## 📱 Mobile App Integration

The HASET Android app integrates with this backend using Retrofit. See the API documentation for complete integration examples including:

- Retrofit setup
- API service interfaces
- Request/response models
- Repository implementation
- ViewModel updates

## 🤝 Contributing

1. Create a feature branch
2. Make your changes
3. Test thoroughly
4. Submit a pull request

## 📝 License

Proprietary - HASET Health Application

## 👥 Team

Developed for the HASET mobile health platform.

## 📞 Support

For issues or questions, contact the development team.

---

**Version:** 1.0.0  
**Last Updated:** 2026-01-23  
**Status:** Development
