<?php

require_once __DIR__ . '/../../vendor/autoload.php';

// Test email
$email = "valid.email1@example.com";

// Create a simple HTTP client
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/api/verify");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['text' => $email]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Output the results
echo "HTTP Code: $httpCode\n";
echo "Response:\n";
echo $response;
echo "\n";

// Parse the JSON response
$data = json_decode($response, true);

// Check if the email is valid
if (isset($data['data']['emails'][0]['status'])) {
    $status = $data['data']['emails'][0]['status'];
    echo "\nEmail status: $status\n";
    if ($status === 'valid') {
        echo "✅ Email is valid\n";
    } else {
        echo "❌ Email is invalid\n";
    }
} else {
    echo "\nCould not determine email status from response\n";
}
