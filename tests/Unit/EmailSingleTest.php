<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\EmailExtractor;
use App\EmailValidator;

// Test email
$email = "valid.email1@example.com";

// Extract email
$extractor = new EmailExtractor();
$emails = $extractor->extractEmails($email);

echo "Extracted emails: " . count($emails) . "\n";
echo "Emails: " . implode(", ", $emails) . "\n\n";

// Validate email
$validator = new EmailValidator();
$results = $validator->validate($emails);

echo "Validation results:\n";
foreach ($results as $result) {
    $status = $result['status'] === 'valid' ? '✅ valid' : '❌ invalid';
    echo "{$result['email']} - {$status}\n";
}
