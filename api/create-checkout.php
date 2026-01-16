<?php

// Load Stripe library
require_once __DIR__ . '/../vendor/autoload.php';

\Stripe\Stripe::setApiKey('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');

// Set CORS headers to allow your website to call this
header('Access-Control-Allow-Origin: https://www.formcard.com');
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
