# BagVoyage - Logistics Website Setup Guide

## Overview

BagVoyage is a comprehensive logistics website built with Laravel that integrates with FedEx APIs for shipping services and PayPal for secure payments. This application allows customers to:

- Get shipping quotes from FedEx with real-time rates
- Schedule pickups or generate drop-off labels
- Pay securely through PayPal
- Track shipments (optional)

## Features

✅ **Homepage with Origin/Destination Form**
- Hero section with country selection
- Modern, responsive design

✅ **Detailed Shipment Form**
- Complete sender and recipient information
- Package dimensions and weight
- Pickup vs drop-off options
- Shipping preferences

✅ **FedEx Integration**
- Real-time rate calculation
- 10% handling fee automatically added
- Multiple service types (Standard, Express, Overnight)
- Pickup scheduling and label generation

✅ **PayPal Payment Integration**
- Secure payment processing
- Order management
- Transaction tracking

✅ **Responsive Design**
- Mobile-friendly interface
- Tailwind CSS styling
- Modern UI/UX

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm
- SQLite (or MySQL/PostgreSQL)
- FedEx Developer Account
- PayPal Developer Account

## Installation

1. **Clone the repository**
```bash
git clone <your-repo-url>
cd Bagvoyaage
```

2. **Install PHP dependencies**
```bash
composer install
```

3. **Install Node.js dependencies**
```bash
npm install
```

4. **Create environment file**
```bash
cp .env.example .env
```

5. **Generate application key**
```bash
php artisan key:generate
```

6. **Create database and run migrations**
```bash
php artisan migrate
```

7. **Build frontend assets**
```bash
npm run build
```

## Environment Configuration

Add the following variables to your `.env` file:

### FedEx API Configuration
```env
FEDEX_BASE_URL=https://apis-sandbox.fedex.com
FEDEX_API_KEY=your_fedex_api_key_here
FEDEX_SECRET_KEY=your_fedex_secret_key_here
FEDEX_ACCOUNT_NUMBER=your_fedex_account_number_here
FEDEX_METER_NUMBER=your_fedex_meter_number_here
```

### PayPal Configuration
```env
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=your_paypal_client_id_here
PAYPAL_CLIENT_SECRET=your_paypal_client_secret_here
```

## API Account Setup

### FedEx Developer Account

1. Visit [FedEx Developer Portal](https://developer.fedex.com/)
2. Create a developer account
3. Create a new application
4. Get your API credentials:
   - API Key
   - Secret Key
   - Account Number
   - Meter Number

### PayPal Developer Account

1. Visit [PayPal Developer Portal](https://developer.paypal.com/)
2. Create a developer account
3. Create a new application
4. Get your credentials:
   - Client ID
   - Client Secret

## Running the Application

1. **Start the Laravel development server**
```bash
php artisan serve
```

2. **Start the Vite development server (in another terminal)**
```bash
npm run dev
```

3. **Access the application**
Open your browser and navigate to `http://localhost:8000`

## Database Schema

The application uses the following main tables:

- `shipments` - Main shipment records
- `shipment_rates` - FedEx rate information
- `payment_transactions` - PayPal payment records
- `shipment_trackings` - Optional tracking updates

## File Structure

```
app/
├── Http/Controllers/
│   └── ShipmentController.php    # Main controller
├── Models/
│   ├── Shipment.php             # Shipment model
│   ├── ShipmentRate.php         # Rate model
│   ├── PaymentTransaction.php   # Payment model
│   └── ShipmentTracking.php     # Tracking model
└── Services/
    ├── FedExService.php         # FedEx API integration
    └── PayPalService.php        # PayPal integration

resources/views/
├── layout.blade.php             # Base layout
└── shipment/
    ├── index.blade.php          # Homepage
    ├── create.blade.php         # Detailed form
    ├── quote.blade.php          # Rate selection
    ├── checkout.blade.php       # Payment page
    └── success.blade.php        # Success page
```

## API Documentation

### FedEx APIs Used
- [Rate API](https://developer.fedex.com/api/en-us/catalog/rate/v1/docs.html) - Get shipping rates
- [Ship API](https://developer.fedex.com/api/en-us/catalog/ship/v1/docs.html) - Create shipments
- [Pickup API](https://developer.fedex.com/api/en-us/catalog/pickup/v1/docs.html) - Schedule pickups
- [Track API](https://developer.fedex.com/api/en-us/catalog/track/v1/docs.html) - Track packages

### PayPal APIs Used
- [Orders API](https://developer.paypal.com/docs/api/orders/v2/) - Create and capture payments

## Features Explained

### Rate Calculation
- Fetches real-time rates from FedEx
- Adds 10% handling fee automatically
- Displays multiple service options
- Shows estimated delivery times

### Payment Processing
- Creates PayPal orders
- Handles payment confirmation
- Updates shipment status
- Records transaction details

### Shipment Scheduling
- Supports both pickup and drop-off
- Integrates with FedEx scheduling API
- Generates shipping labels
- Sends confirmation emails

## Customization

### Adding New Countries
The application uses the `league/iso3166` package for country codes. All countries are automatically available.

### Modifying Handling Fee
Edit the handling fee percentage in `app/Services/FedExService.php`:
```php
$handlingFee = $baseRate * 0.10; // Change 0.10 to your desired percentage
```

### Styling Changes
The application uses Tailwind CSS. Modify classes in Blade templates or add custom CSS in `resources/css/app.css`.

## Troubleshooting

### Common Issues

1. **FedEx API Errors**
   - Verify API credentials in `.env`
   - Check FedEx account status
   - Ensure proper address formats

2. **PayPal Payment Failures**
   - Verify PayPal credentials
   - Check sandbox vs production mode
   - Ensure proper currency codes

3. **Database Errors**
   - Run `php artisan migrate:fresh` to reset database
   - Check database permissions
   - Verify SQLite file creation

### Debug Mode
Enable debug mode in `.env`:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

## Security Considerations

- Never commit API credentials to version control
- Use HTTPS in production
- Validate all input data
- Implement rate limiting
- Regular security updates

## Production Deployment

1. Set `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false`
3. Use production FedEx and PayPal URLs
4. Configure proper database (MySQL/PostgreSQL)
5. Set up SSL certificates
6. Configure proper mail settings
7. Implement proper error logging

## Support

For technical support or questions:
- Check the Laravel documentation
- Review FedEx API documentation
- Check PayPal developer resources
- Create issues in the project repository

## License

This project is licensed under the MIT License. 
