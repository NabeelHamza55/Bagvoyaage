<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('subject', 'BagVoyage Shipping Notification')</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }

        /* Main styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #374151;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 40px;
            text-align: center;
        }

        .email-header h1 {
            color: #ffffff;
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .email-header .tagline {
            color: #e5e7eb;
            font-size: 16px;
            margin: 8px 0 0 0;
        }

        .email-body {
            padding: 40px;
        }

        .email-footer {
            background-color: #f9fafb;
            padding: 30px 40px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-confirmed {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-shipped {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-delivered {
            background-color: #fef3c7;
            color: #92400e;
        }

        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .shipping-details {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 24px;
            margin: 24px 0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #374151;
        }

        .detail-value {
            color: #6b7280;
        }

        .tracking-number {
            font-family: 'Courier New', monospace;
            background-color: #e5e7eb;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        .social-links {
            margin-top: 20px;
        }

        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #6b7280;
            text-decoration: none;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }

            .email-header,
            .email-body,
            .email-footer {
                padding: 20px !important;
            }

            .email-header h1 {
                font-size: 24px !important;
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .detail-value {
                margin-top: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>BagVoyage</h1>
            <p class="tagline">International Shipping Made Easy</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            @yield('content')
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p style="margin: 0 0 16px 0; color: #6b7280; font-size: 14px;">
                Thank you for choosing BagVoyage for your international shipping needs.
            </p>

            <p style="margin: 0 0 16px 0; color: #6b7280; font-size: 14px;">
                Questions? Contact us at
                <a href="mailto:support@bagvoyage.com" style="color: #667eea;">support@bagvoyage.com</a>
                or call +1 (555) 123-4567
            </p>

            <div class="social-links">
                <a href="#" title="Twitter">Twitter</a>
                <a href="#" title="Facebook">Facebook</a>
                <a href="#" title="LinkedIn">LinkedIn</a>
            </div>

            <p style="margin: 16px 0 0 0; color: #9ca3af; font-size: 12px;">
                &copy; {{ date('Y') }} BagVoyage. All rights reserved.<br>
                This email was sent to {{ $shipment->sender_email ?? 'you' }} regarding tracking #{{ $shipment->tracking_number ?? 'N/A' }}
            </p>
        </div>
    </div>
</body>
</html>
