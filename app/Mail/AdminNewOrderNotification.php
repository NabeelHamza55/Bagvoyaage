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

class AdminNewOrderNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $shipment;
    public $attachmentPath;

    /**
     * Create a new message instance.
     */
    public function __construct(Shipment $shipment, ?string $attachmentPath = null)
    {
        $this->shipment = $shipment;
        $this->attachmentPath = $attachmentPath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@bagvoyaage.org', 'BagVoyage Admin'),
            subject: 'New Order Alert - Order #' . $this->shipment->id . ' - ' . $this->shipment->tracking_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.new_order',
            with: [
                'shipment' => $this->shipment,
                'customerName' => $this->shipment->sender_full_name,
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
