# BagVoyage - Shipping Management Application

BagVoyage is a comprehensive shipping management application that integrates with FedEx for shipping services and PayPal for payment processing.

## Features

- **Shipping Rate Calculation**: Get accurate shipping rates from FedEx based on package details and destination.
- **Multiple Service Options**: Choose from various shipping service levels (Ground, Express, Overnight).
- **Secure Payments**: Process payments securely through PayPal.
- **Shipment Tracking**: Track packages with real-time status updates.
- **Pickup Scheduling**: Schedule package pickups at your convenience.
- **Label Generation**: Generate and download shipping labels directly from the application.

## Recent API Fixes

We've recently fixed issues with both the FedEx and PayPal APIs:

### FedEx API Fixes
- Added the required `rateRequestType` parameter to prevent API errors
- Implemented package dimension and weight limits to prevent the `PACKAGE.DIMENSIONS.EXCEEDED` error
- Added user-friendly error messages for common API issues
- Improved error logging for better troubleshooting
- **NEW**: Removed the hardcoded service type to show all available FedEx shipping options
- **NEW**: Enhanced the rate display with categorized service types (Ground, Express, Overnight)
- **NEW**: Improved transit time calculation and display
- **NEW**: Enhanced label generation with proper PDF formatting
- **NEW**: Improved pickup scheduling with better error handling
- **NEW**: Fixed "Invalid field value in the input" error with better validation
- **NEW**: Added phone number formatting to meet FedEx API requirements
- **NEW**: Improved shipment status handling with proper enum values
- **NEW**: Implemented separate FedEx shipment creation and pickup scheduling flows
- **NEW**: Added dedicated views for shipment creation, pickup scheduling, and label generation

### PayPal API Fixes
- Enhanced error handling and user feedback for payment issues
- Added credential verification before payment processing
- Improved logging of API responses and errors
- Added test endpoints for API connectivity verification
- **NEW**: Fixed PayPal redirection issues after payment completion
  - Fixed empty JSON body issue in capture request
  - Improved token handling in the return URL
  - Enhanced logging of payment flow for better debugging
- **NEW**: Added better error handling for payment capture failures
- **NEW**: Improved transaction status tracking and management
- **NEW**: Fixed missing views for payment success and cancellation pages
- **NEW**: Added gateway_data storage for PayPal token and PayerID

To test API connectivity, visit:
- `/test/fedex` - Tests FedEx API authentication
- `/test/paypal` - Tests PayPal API authentication
- `/test/paypal-redirect` - Tests PayPal redirection URLs
- `/test/payment-success` - Simulates a complete PayPal payment flow

## Shipping Workflow

The application follows a streamlined shipping workflow:

### Complete Flow (One-Time Payment Process)

1. **State Selection**: User selects origin and destination states
2. **Shipment Form**: User fills out complete shipment details (sender, recipient, package information)
3. **Rate Calculation**: System fetches real-time FedEx shipping rates
4. **Rate Selection**: User selects preferred shipping service and rate
5. **Single Checkout**: User completes PayPal payment (ONE TIME ONLY)
6. **Automatic Shipment Creation**: After successful payment, FedEx shipment is automatically created
7. **Shipment Details Page**: User is redirected to a comprehensive page with:
   - Tracking number
   - Shipping label download
   - Pickup scheduling options (if pickup delivery method)
   - Package tracking

### Key Features

- **No Multiple Checkouts**: Payment happens only once - after rate selection
- **Automatic Processing**: Shipment creation happens automatically after payment
- **Immediate Access**: Users get immediate access to tracking and labels
- **Optional Pickup**: Pickup scheduling is optional and separate from the main flow

### Delivery Methods

**Drop-off Method:**
- User downloads shipping label immediately
- Takes package to any FedEx location
- Package enters FedEx network

**Pickup Method:**
- User can optionally schedule pickup from the shipment details page
- FedEx picks up package from user's address
- Pickup confirmation provided

## Requirements

- PHP 8.1 or higher
- MySQL 5.7 or higher
- Composer
- Node.js and NPM
- Valid FedEx API credentials
- Valid PayPal API credentials

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/bagvoyaage.git
   cd bagvoyaage
   ```

2. Install PHP dependencies:
   ```
   composer install
   ```

3. Install JavaScript dependencies:
   ```
   npm install
   ```

4. Create a copy of the `.env.example` file:
   ```
   cp .env.example .env
   ```

5. Generate an application key:
   ```
   php artisan key:generate
   ```

6. Configure your database in the `.env` file:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=bagvoyaage
   DB_USERNAME=root
   DB_PASSWORD=
   ```

7. Run the database migrations:
   ```
   php artisan migrate
   ```

8. Build the frontend assets:
   ```
   npm run build
   ```

9. Start the development server:
   ```
   php artisan serve
   ```

## API Configuration

### FedEx API Setup

1. Sign up for a [FedEx Developer Account](https://developer.fedex.com/)
2. Create a new application to get your API credentials
3. Update your `.env` file with your FedEx API credentials:
   ```
   FEDEX_BASE_URL=https://apis-sandbox.fedex.com
   FEDEX_API_KEY=your_fedex_api_key
   FEDEX_SECRET_KEY=your_fedex_secret_key
   FEDEX_ACCOUNT_NUMBER=your_fedex_account_number
   FEDEX_METER_NUMBER=your_fedex_meter_number
   ```

#### FedEx API Troubleshooting

- **Authentication Issues**: Ensure your API key and secret key are correct and not expired
- **Account Number Format**: The account number should be a 9-digit number without dashes
- **Rate API Errors**: When getting rate errors, check that all required parameters are included:
  - Make sure `rateRequestType` is properly set in the payload
  - Verify all address information is valid
  - Check package dimensions and weight are in the correct format
- **Label Generation Issues**:
  - Ensure the shipment has a valid tracking number
  - Verify that the label specification parameters are correct
  - Check that the recipient address is complete and valid
  - Make sure the package dimensions and weight are within FedEx limits
- **Pickup Scheduling Problems**:
  - Verify the pickup address is complete and valid
  - Ensure the pickup date is in the future and formatted correctly
  - Check that your account has pickup privileges
  - Verify the package details match the shipment information
- **Sandbox vs Production**: Double-check you're using the correct base URL for your environment

### PayPal API Setup

1. Sign up for a [PayPal Developer Account](https://developer.paypal.com/)
2. Create a new application in the PayPal Developer Dashboard:
   - Go to "My Apps & Credentials"
   - Click "Create App" under the REST API apps section
   - Name your application (e.g., "BagVoyage Shipping")
   - Select "Merchant" as the app type
   - Click "Create App"
3. Copy your Client ID and Secret from the app details page
4. Update your `.env` file with your PayPal API credentials:
   ```
   PAYPAL_MODE=sandbox
   PAYPAL_CLIENT_ID=your_paypal_client_id
   PAYPAL_CLIENT_SECRET=your_paypal_client_secret
   ```
5. For webhook support, create a webhook in your PayPal Developer Dashboard:
   - Go to "My Apps & Credentials"
   - Select your app
   - Scroll down to "Webhooks"
   - Click "Add Webhook"
   - Enter your webhook URL (e.g., https://yourdomain.com/webhook/paypal)
   - Select the events you want to receive (at minimum: PAYMENT.CAPTURE.COMPLETED, CHECKOUT.ORDER.APPROVED)
   - Copy the Webhook ID and add it to your `.env` file:
   ```
   PAYPAL_WEBHOOK_ID=your_paypal_webhook_id
   ```

#### PayPal API Troubleshooting

- **Authentication Issues**: 
  - Verify your Client ID and Secret are correct
  - Make sure you're using the right mode (sandbox/live)
  - Check if your credentials have expired or been revoked
  
- **Payment Flow Problems**:
  - Ensure your return and cancel URLs are correctly configured
  - Verify that the order creation is successful before redirecting
  - Check that your server can receive PayPal's webhook notifications
  - If redirection after payment isn't working:
    - Verify that your return URL is properly registered with PayPal
    - Check that the return URL includes the necessary parameters (shipment, token, PayerID)
    - Make sure your server is accessible from PayPal's servers
    - Check your server logs for any redirection errors
  
- **Common Error Codes**:
  - `INVALID_CLIENT`: Client ID or secret is incorrect
  - `INVALID_REQUEST`: Missing required parameters in the request
  - `PERMISSION_DENIED`: Your app doesn't have permission for the requested action
  - `INTERNAL_SERVER_ERROR`: PayPal is experiencing issues; try again later
  - `MALFORMED_REQUEST_JSON`: This often occurs when sending an empty array `[]` instead of an empty object `{}` in JSON requests. Always use `(object)[]` or `{}` for empty JSON bodies.

- **Testing Payments**:
  - Use PayPal's sandbox testing accounts for testing payments
  - Create both buyer and seller test accounts in the PayPal Developer Dashboard
  - For testing redirection, use the `/test/paypal-redirect` endpoint to verify URL formats
  - To simulate a complete payment flow, use the `/test/payment-success` endpoint

## Logging and Debugging

The application logs API interactions with both FedEx and PayPal. Check the logs at:
```
storage/logs/laravel.log
```

To enable more detailed logging, update your `.env` file:
```
LOG_LEVEL=debug
```

## Production Deployment

For production deployment, update your `.env` file with the following settings:

```
APP_ENV=production
APP_DEBUG=false
PAYPAL_MODE=live
FEDEX_BASE_URL=https://apis.fedex.com
```

Make sure to use your production API credentials for both FedEx and PayPal.

## Security Considerations

- Always use HTTPS in production
- Ensure your server meets all security requirements
- Keep your API credentials secure and never commit them to version control
- Regularly update dependencies to patch security vulnerabilities

## License

This project is licensed under the MIT License. See the LICENSE file for details.

## Support

For support or inquiries, please contact support@bagvoyaage.com
