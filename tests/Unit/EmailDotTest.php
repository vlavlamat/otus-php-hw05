<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\EmailExtractor;
use App\EmailValidator;

// Test email with a leading dot
$dotEmail = ".email@domain.com";

echo "Testing email with a leading dot: $dotEmail\n\n";

// Create instances of the classes
$extractor = new EmailExtractor();
$validator = new EmailValidator();

// Test if the email is detected
$hasEmails = $extractor->hasEmails($dotEmail);
echo "Email detected by hasEmails(): " . ($hasEmails ? "Yes" : "No") . "\n";

// Try to extract the email
$extractedEmails = $extractor->extractEmails($dotEmail);
echo "Extracted emails: " . count($extractedEmails) . "\n";
if (count($extractedEmails) > 0) {
    echo "Extracted: " . implode(", ", $extractedEmails) . "\n";
} else {
    echo "No emails extracted\n";
}

// Validate the email directly
$validationResults = $validator->validate([$dotEmail]);
if (count($validationResults) > 0) {
    $status = $validationResults[0]['status'] === 'valid' ? 'valid' : 'invalid';
    echo "Validation result: $status\n";
} else {
    echo "No validation result\n";
}

echo "\n";

// Test with a mix of valid and invalid emails
$mixedEmails = "valid.email1@example.com\n.invalid.email@domain.com";
echo "Testing with mixed emails:\n$mixedEmails\n\n";

// Check if any emails are detected
$hasEmails = $extractor->hasEmails($mixedEmails);
echo "Emails detected by hasEmails(): " . ($hasEmails ? "Yes" : "No") . "\n";

// Extract emails
$extractedEmails = $extractor->extractEmails($mixedEmails);
echo "Extracted emails: " . count($extractedEmails) . "\n";
if (count($extractedEmails) > 0) {
    echo "Extracted: " . implode(", ", $extractedEmails) . "\n";
} else {
    echo "No emails extracted\n";
}
