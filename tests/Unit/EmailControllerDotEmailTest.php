<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\EmailExtractor;
use App\EmailValidator;

// Test email with a leading dot
$dotEmail = ".email@domain.com";

// Create instances of the classes
$extractor = new EmailExtractor();
$validator = new EmailValidator();

// Simulate what happens in the controller
echo "Testing with a single email with a leading dot: $dotEmail\n\n";

// Extract emails
$emails = $extractor->extractEmails($dotEmail);

// Check if emails were found
if (empty($emails)) {
    echo "Email addresses not found\n";
} else {
    echo "Emails found: " . count($emails) . "\n";

    // Validate emails
    $validationResults = $validator->validate($emails);

    foreach ($validationResults as $result) {
        $status = $result['status'] === 'valid' ? 'valid' : 'invalid';
        echo "Email: {$result['email']} - Status: $status\n";
    }
}

// Test with a mix of valid and invalid emails
$mixedEmails = "valid.email1@example.com\n.invalid.email@domain.com";
echo "\nTesting with mixed emails:\n$mixedEmails\n\n";

// Extract emails
$emails = $extractor->extractEmails($mixedEmails);

// Check if emails were found
if (empty($emails)) {
    echo "Email addresses not found\n";
} else {
    echo "Emails found: " . count($emails) . "\n";

    // Validate emails
    $validationResults = $validator->validate($emails);

    foreach ($validationResults as $result) {
        $status = $result['status'] === 'valid' ? 'valid' : 'invalid';
        echo "Email: {$result['email']} - Status: $status\n";
    }
}
