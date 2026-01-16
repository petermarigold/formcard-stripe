<?php
// version 3
// Load Stripe library
require_once __DIR__ . '/../vendor/autoload.php';

\Stripe\Stripe::setApiKey('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');

// Set CORS headers - now handled by vercel.json but keeping for safety
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($input['amount']) || $input['amount'] < 30) {
        throw new Exception('Amount must be at least 30 pence');
    }
    
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'gbp',
                'product_data' => [
                    'name' => 'FORMcard Custom Colour Pack',
                    'description' => $input['pack_count'] . ' packs (' . ($input['pack_count'] * 3) . ' cards)',
                ],
                'unit_amount' => (int)$input['amount'], // Amount in pence - cast to integer
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'https://www.formcard.com/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://www.formcard.com/shop-test-stripe',
        'metadata' => [
            'order_details' => $input['order_details'],
            'promo_code' => $input['promo_code'] ?? '',
            'full_price' => $input['full_price'],
            'discount' => $input['discount'],
        ],
    ]);

    echo json_encode(['id' => $session->id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
