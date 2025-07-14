<?php

namespace App\Services;

use App\Mail\ShippingNotification;
use App\Models\Shipment;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send shipping confirmation email
     */
    public function sendShippingConfirmation(Shipment $shipment): bool
    {
        try {
            Mail::to($shipment->sender_email)
                ->cc($shipment->recipient_email)
                ->send(new ShippingNotification($shipment, 'confirmation'));

            Log::info('Shipping confirmation email sent', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'recipient' => $shipment->sender_email
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send shipping confirmation email: ' . $e->getMessage(), [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send shipping label email with attachment
     */
    public function sendShippingLabel(Shipment $shipment, string $labelPath = null): bool
    {
        try {
            Mail::to($shipment->sender_email)
                ->send(new ShippingNotification($shipment, 'label', $labelPath));

            Log::info('Shipping label email sent', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'recipient' => $shipment->sender_email,
                'has_attachment' => !empty($labelPath)
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send shipping label email: ' . $e->getMessage(), [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send pickup scheduled notification
     */
    public function sendPickupScheduled(Shipment $shipment): bool
    {
        try {
            Mail::to($shipment->sender_email)
                ->send(new ShippingNotification($shipment, 'pickup_scheduled'));

            Log::info('Pickup scheduled email sent', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'recipient' => $shipment->sender_email
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send pickup scheduled email: ' . $e->getMessage(), [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send package shipped notification
     */
    public function sendPackageShipped(Shipment $shipment): bool
    {
        try {
            Mail::to($shipment->sender_email)
                ->cc($shipment->recipient_email)
                ->send(new ShippingNotification($shipment, 'shipped'));

            Log::info('Package shipped email sent', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'recipients' => [$shipment->sender_email, $shipment->recipient_email]
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send package shipped email: ' . $e->getMessage(), [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send package delivered notification
     */
    public function sendPackageDelivered(Shipment $shipment): bool
    {
        try {
            Mail::to($shipment->sender_email)
                ->cc($shipment->recipient_email)
                ->send(new ShippingNotification($shipment, 'delivered'));

            Log::info('Package delivered email sent', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'recipients' => [$shipment->sender_email, $shipment->recipient_email]
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send package delivered email: ' . $e->getMessage(), [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send shipping exception notification
     */
    public function sendShippingException(Shipment $shipment, string $exceptionMessage = ''): bool
    {
        try {
            Mail::to($shipment->sender_email)
                ->cc($shipment->recipient_email)
                ->send(new ShippingNotification($shipment, 'exception'));

            Log::info('Shipping exception email sent', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'exception' => $exceptionMessage,
                'recipients' => [$shipment->sender_email, $shipment->recipient_email]
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send shipping exception email: ' . $e->getMessage(), [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send payment confirmation email
     */
    public function sendPaymentConfirmation(Shipment $shipment): bool
    {
        try {
            // Use general notification type for payment confirmations
            Mail::to($shipment->sender_email)
                ->send(new ShippingNotification($shipment, 'confirmation'));

            Log::info('Payment confirmation email sent', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'recipient' => $shipment->sender_email
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send payment confirmation email: ' . $e->getMessage(), [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send multiple notification types
     */
    public function sendMultipleNotifications(Shipment $shipment, array $types): array
    {
        $results = [];

        foreach ($types as $type) {
            switch ($type) {
                case 'confirmation':
                    $results[$type] = $this->sendShippingConfirmation($shipment);
                    break;
                case 'label':
                    $results[$type] = $this->sendShippingLabel($shipment);
                    break;
                case 'pickup_scheduled':
                    $results[$type] = $this->sendPickupScheduled($shipment);
                    break;
                case 'shipped':
                    $results[$type] = $this->sendPackageShipped($shipment);
                    break;
                case 'delivered':
                    $results[$type] = $this->sendPackageDelivered($shipment);
                    break;
                case 'exception':
                    $results[$type] = $this->sendShippingException($shipment);
                    break;
                case 'payment':
                    $results[$type] = $this->sendPaymentConfirmation($shipment);
                    break;
                default:
                    $results[$type] = false;
                    Log::warning('Unknown notification type: ' . $type);
            }
        }

        return $results;
    }

    /**
     * Test email configuration
     */
    public function testEmailConfiguration(): bool
    {
        try {
            $testEmail = config('mail.from.address');

            if (empty($testEmail)) {
                Log::error('No mail from address configured');
                return false;
            }

            // Create a test shipment object
            $testShipment = new Shipment([
                'tracking_number' => 'TEST-' . uniqid(),
                'sender_name' => 'Test Sender',
                'sender_email' => $testEmail,
                'recipient_name' => 'Test Recipient',
                'recipient_email' => $testEmail,
                'origin_country' => 'US',
                'destination_country' => 'CA',
                'status' => 'confirmed'
            ]);

            Mail::to($testEmail)
                ->send(new ShippingNotification($testShipment, 'confirmation'));

            Log::info('Test email sent successfully', ['email' => $testEmail]);
            return true;

        } catch (Exception $e) {
            Log::error('Email configuration test failed: ' . $e->getMessage());
            return false;
        }
    }
}
