<?php

require_once __DIR__ . '/vendor/autoload.php';

// Test email with a leading dot
$dotEmail = ".email@domain.com";

// Create a simple HTTP client
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/api/verify");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['text' => $dotEmail]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Output the results
echo "Testing API with a single email with a leading dot: $dotEmail\n";
echo "HTTP Code: $httpCode\n";
echo "Response:\n";
echo $response;
echo "\n\n";

// Parse the JSON response
$data = json_decode($response, true);

// Check if emails were found
if (isset($data['data']['emails']) && count($data['data']['emails']) > 0) {
    echo "Emails found: " . count($data['data']['emails']) . "\n";
    foreach ($data['data']['emails'] as $email) {
        $status = $email['status'] === 'valid' ? 'valid' : 'invalid';
        echo "Email: {$email['email']} - Status: $status\n";
    }
} else {
    echo "No emails found in the response\n";
}