<?php

require_once __DIR__ . '/vendor/autoload.php';

// Test case: Email with a leading dot and its regular counterpart
$testCase = ".email@domain.com
email@domain.com";

echo "Test Case: Email with a leading dot and its regular counterpart\n";
echo "Input:\n$testCase\n\n";

// Create a simple HTTP client
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/api/verify");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['text' => $testCase]));
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

// Check the validation results
if (isset($data['data']['emails']) && is_array($data['data']['emails'])) {
    echo "\nValidation results:\n";
    foreach ($data['data']['emails'] as $result) {
        $status = $result['status'] === 'valid' ? '✅ valid' : '❌ invalid';
        echo "{$result['email']} - {$status}\n";
    }
} else {
    echo "\nCould not determine email status from response\n";
}

// Test case 2: Email with a leading dot without its regular counterpart
$testCase2 = ".email@domain.com";

echo "\n\nTest Case 2: Email with a leading dot without its regular counterpart\n";
echo "Input:\n$testCase2\n\n";

// Create a simple HTTP client
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/api/verify");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['text' => $testCase2]));
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

// Check the validation results
if (isset($data['data']['emails']) && is_array($data['data']['emails'])) {
    echo "\nValidation results:\n";
    foreach ($data['data']['emails'] as $result) {
        $status = $result['status'] === 'valid' ? '✅ valid' : '❌ invalid';
        echo "{$result['email']} - {$status}\n";
    }
} else {
    echo "\nCould not determine email status from response\n";
}