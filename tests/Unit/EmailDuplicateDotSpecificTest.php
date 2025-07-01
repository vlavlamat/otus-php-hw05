<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\EmailExtractor;
use App\EmailValidator;

// Test case: Email with a leading dot and its regular counterpart
$testCase = ".email@domain.com
email@domain.com";

echo "Test Case: Email with a leading dot and its regular counterpart\n";
echo "Input:\n$testCase\n\n";

// Extract and validate emails
$extractor = new EmailExtractor();
$validator = new EmailValidator();

$extracted = $extractor->extractEmails($testCase);
echo "Extracted emails: " . count($extracted) . "\n";
echo "Extracted emails list:\n";
foreach ($extracted as $email) {
    echo "- $email\n";
}
echo "\n";

$results = $validator->validate($extracted);
echo "Validation results:\n";
foreach ($results as $result) {
    $status = $result['status'] === 'valid' ? '✅ valid' : '❌ invalid';
    echo "{$result['email']} - {$status}\n";
}

// Let's also check if the issue is in the EmailValidator class
echo "\nTesting EmailValidator directly:\n";
$dotEmail = ".email@domain.com";
$regularEmail = "email@domain.com";

echo "Validating dot email directly: $dotEmail\n";
$dotResult = $validator->validate([$dotEmail]);
$dotStatus = $dotResult[0]['status'] === 'valid' ? '✅ valid' : '❌ invalid';
echo "$dotEmail - $dotStatus\n";

echo "Validating regular email directly: $regularEmail\n";
$regularResult = $validator->validate([$regularEmail]);
$regularStatus = $regularResult[0]['status'] === 'valid' ? '✅ valid' : '❌ invalid';
echo "$regularEmail - $regularStatus\n";

// Test if the order matters
echo "\nTesting if order matters:\n";
$results1 = $validator->validate([$dotEmail, $regularEmail]);
echo "Order: dot email, then regular email\n";
foreach ($results1 as $result) {
    $status = $result['status'] === 'valid' ? '✅ valid' : '❌ invalid';
    echo "{$result['email']} - {$status}\n";
}

$results2 = $validator->validate([$regularEmail, $dotEmail]);
echo "\nOrder: regular email, then dot email\n";
foreach ($results2 as $result) {
    $status = $result['status'] === 'valid' ? '✅ valid' : '❌ invalid';
    echo "{$result['email']} - {$status}\n";
}
