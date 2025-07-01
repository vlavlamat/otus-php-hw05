<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\EmailExtractor;

// Test string with multiple @ symbols
$testString = "user@mail@domain.com";

// Extract emails
$extractor = new EmailExtractor();
$emails = $extractor->extractEmails($testString);

echo "Input: $testString\n";
echo "Extracted emails: " . count($emails) . "\n";
echo "Emails: " . implode(", ", $emails) . "\n";

// Test with a list of problematic emails
$testList = "
valid.email1@example.com
user@mail@domain.com
test!user@mail.com
email@domain.com
";

$emails = $extractor->extractEmails($testList);

echo "\nInput list:\n$testList\n";
echo "Extracted emails: " . count($emails) . "\n";
echo "Emails: " . implode(", ", $emails) . "\n";
