<?php

namespace App\Mail;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class ShippingNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $shipment;
    public $type;
    public $attachmentPath;

    /**
     * Create a new message instance.
     */
    public function __construct(Shipment $shipment, string $type = 'confirmation', ?string $attachmentPath = null)
    {
        $this->shipment = $shipment;
        $this->type = $type;
        $this->attachmentPath = $attachmentPath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->type) {
            'confirmation' => 'Shipping Order Confirmed - ' . $this->shipment->tracking_number,
            'label' => 'Shipping Label Ready - ' . $this->shipment->tracking_number,
            'pickup_scheduled' => 'Pickup Scheduled - ' . $this->shipment->tracking_number,
            'shipped' => 'Package Shipped - ' . $this->shipment->tracking_number,
            'delivered' => 'Package Delivered - ' . $this->shipment->tracking_number,
            'exception' => 'Shipping Update - ' . $this->shipment->tracking_number,
            default => 'Shipping Update - ' . $this->shipment->tracking_number,
        };

        return new Envelope(
            from: new Address('noreply@bagvoyage.com', 'BagVoyage Shipping'),
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $view = match($this->type) {
            'confirmation' => 'emails.shipping.confirmation',
            'label' => 'emails.shipping.label',
            'pickup_scheduled' => 'emails.shipping.pickup_scheduled',
            'shipped' => 'emails.shipping.shipped',
            'delivered' => 'emails.shipping.delivered',
            'exception' => 'emails.shipping.exception',
            default => 'emails.shipping.general',
        };

        return new Content(
            view: $view,
            with: [
                'shipment' => $this->shipment,
                'type' => $this->type,
                'customerName' => $this->shipment->sender_name,
                'trackingNumber' => $this->shipment->tracking_number,
                'trackingUrl' => route('shipment.track', $this->shipment->tracking_number),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];

        // Add shipping label if available
        if ($this->attachmentPath && file_exists($this->attachmentPath)) {
            $attachments[] = Attachment::fromPath($this->attachmentPath)
                ->as('shipping_label.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
