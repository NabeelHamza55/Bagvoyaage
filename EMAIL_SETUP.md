# Email Configuration Setup

## Current Issue
The application is currently configured to use the 'log' mail driver, which means emails are being logged to files instead of being sent to recipients.

## Quick Fix

### 1. Create a .env file in the project root with the following content:

```env
APP_NAME=BagVoyage
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bagvoyage
DB_USERNAME=your-db-username
DB_PASSWORD=your-db-password

# Email Configuration - Choose one option below:

# Option 1: Gmail SMTP
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@bagvoyage.com
MAIL_FROM_NAME="BagVoyage"

# Option 2: Mailgun (Recommended for production)
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.mailgun.org
MAILGUN_SECRET=your-mailgun-secret
MAIL_FROM_ADDRESS=noreply@bagvoyage.com
MAIL_FROM_NAME="BagVoyage"

# Option 3: SendGrid
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@bagvoyage.com
MAIL_FROM_NAME="BagVoyage"

# FedEx API Configuration
FEDEX_API_KEY=your-fedex-api-key
FEDEX_SECRET_KEY=your-fedex-secret-key
FEDEX_ACCOUNT_NUMBER=your-fedex-account-number
FEDEX_METER_NUMBER=your-fedex-meter-number
FEDEX_BASE_URL=https://apis-sandbox.fedex.com
```

### 2. Generate Application Key
Run this command to generate the APP_KEY:
```bash
php artisan key:generate
```

### 3. Test Email Configuration
Run the test script to verify emails are working:
```bash
php test_email_system.php
```

## Email Service Options

### Gmail SMTP (Free)
- Use your Gmail account
- Enable 2-factor authentication
- Generate an App Password
- Use the App Password as MAIL_PASSWORD

### Mailgun (Recommended for Production)
- Sign up at mailgun.com
- Verify your domain
- Use the provided credentials
- More reliable for production use

### SendGrid (Alternative)
- Sign up at sendgrid.com
- Create an API key
- Use the API key as MAIL_PASSWORD

## Admin Email Recipients
The following admin emails will receive notifications for every new order:
- mhaammadkhan@gmail.com
- admin@bagvoyaage.org
- nabeelhamza.dev@gmail.com

## Troubleshooting

### If emails are still not being received:
1. Check the Laravel log: `storage/logs/laravel.log`
2. Verify SMTP credentials are correct
3. Check spam/junk folders
4. Ensure the email service is properly configured
5. Test with a simple email first

### Common Issues:
- Gmail: Need to enable "Less secure app access" or use App Passwords
- Mailgun: Domain not verified
- SendGrid: API key not activated
- SMTP: Wrong port or encryption settings

## Testing
After configuration, test the email system by creating a test shipment. The system will send:
1. Customer confirmation email
2. Customer label email (with PDF attachment)
3. Admin notification email (to all 3 admins with PDF attachment)
