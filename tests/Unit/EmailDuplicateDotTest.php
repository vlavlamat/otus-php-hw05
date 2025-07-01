<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\EmailExtractor;
use App\EmailValidator;

// Test case 1: Email with a leading dot and its regular counterpart
$testCase1 = "dot.at.end.@domain.com
.email@domain.com
user@[ipv6:2001:db8::1]
email@domain.com";

echo "Test Case 1: Email with a leading dot and its regular counterpart\n";
echo "Input:\n$testCase1\n\n";

// Extract and validate emails
$extractor = new EmailExtractor();
$validator = new EmailValidator();

$extracted1 = $extractor->extractEmails($testCase1);
echo "Extracted emails: " . count($extracted1) . "\n";

$results1 = $validator->validate($extracted1);
foreach ($results1 as $result) {
    $status = $result['status'] === 'valid' ? '✅ valid' : '❌ invalid';
    echo "{$result['email']} - {$status}\n";
}

echo "\n";

// Test case 2: Email with a leading dot without its regular counterpart
$testCase2 = "dot.at.end.@domain.com
.email@domain.com
user@[ipv6:2001:db8::1]";

echo "Test Case 2: Email with a leading dot without its regular counterpart\n";
echo "Input:\n$testCase2\n\n";

$extracted2 = $extractor->extractEmails($testCase2);
echo "Extracted emails: " . count($extracted2) . "\n";

$results2 = $validator->validate($extracted2);
foreach ($results2 as $result) {
    $status = $result['status'] === 'valid' ? '✅ valid' : '❌ invalid';
    echo "{$result['email']} - {$status}\n";
}
