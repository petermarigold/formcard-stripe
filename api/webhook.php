<?php
file_put_contents('/tmp/webhook-test.log', date('Y-m-d H:i:s') . " - Webhook called!\n", FILE_APPEND);

require_once __DIR__ . '/../vendor/autoload.php';

\Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));

// Webhook secret - we'll add this to Vercel environment variables
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET');

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

header('Content-Type: application/json');

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

// Handle the checkout.session.completed event
if ($event->type == 'checkout.session.completed') {
    $session = $event->data->object;
    
    // Extract order information
    $customerEmail = $session->customer_details->email ?? 'No email provided';
    $customerName = $session->customer_details->name ?? 'Customer';
    $amountPaid = number_format($session->amount_total / 100, 2);
    $orderDetails = $session->metadata->order_details ?? 'No details';
    $promoCode = $session->metadata->promo_code ?? '';
    $sessionId = $session->id;
    
    // Get shipping address
    $shippingAddress = $session->shipping_details->address ?? $session->customer_details->address ?? null;
    $addressText = '';
    if ($shippingAddress) {
        $addressText = "\nShipping Address:\n";
        $addressText .= ($shippingAddress->line1 ?? '') . "\n";
        if (!empty($shippingAddress->line2)) {
            $addressText .= $shippingAddress->line2 . "\n";
        }
        $addressText .= ($shippingAddress->city ?? '') . ", " . 
                       ($shippingAddress->state ?? '') . " " . 
                       ($shippingAddress->postal_code ?? '') . "\n";
        $addressText .= ($shippingAddress->country ?? '') . "\n";
    }
    
    // Email to YOU (the shop owner)
    $toOwner = "orders@formcard.com";
    $subjectOwner = "New FORMcard Order - £{$amountPaid}";
    $messageOwner = "NEW ORDER RECEIVED!

Order ID: {$sessionId}
Amount: £{$amountPaid}" . ($promoCode ? " (Promo: {$promoCode})" : "") . "

Customer: {$customerName}
Email: {$customerEmail}

{$addressText}

COLOUR SELECTIONS:
{$orderDetails}

---
View in Stripe: https://dashboard.stripe.com/payments/{$session->payment_intent}
    ";
    
    $headersOwner = "From: FORMcard Shop <orders@formcard.com>\r\n";
    $headersOwner .= "Reply-To: {$customerEmail}\r\n";
    
    // Email to CUSTOMER
    $toCustomer = $customerEmail;
    $subjectCustomer = "Order Confirmation - FORMcard Custom Colour Pack";
    $messageCustomer = "Hi {$customerName},

Thank you for your FORMcard order!

ORDER DETAILS:
Order ID: {$sessionId}
Amount Paid: £{$amountPaid}

YOUR COLOUR SELECTIONS:
{$orderDetails}

{$addressText}

We'll get your custom colour packs made and shipped out to you as soon as possible!

If you have any questions, just reply to this email.

Thanks,
The FORMcard Team
www.formcard.com
    ";
    
    $headersCustomer = "From: FORMcard <orders@formcard.com>\r\n";
    $headersCustomer .= "Reply-To: orders@formcard.com\r\n";
    
    // Send emails
    mail($toOwner, $subjectOwner, $messageOwner, $headersOwner);
    mail($toCustomer, $subjectCustomer, $messageCustomer, $headersCustomer);
    
    error_log("Order email sent for session: {$sessionId}");
}

http_response_code(200);
?>
