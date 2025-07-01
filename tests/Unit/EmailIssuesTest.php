<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\EmailExtractor;
use App\EmailValidator;

// Test emails
$testEmails = [
    'test!user@mail.com',
    '.email@domain.com'
];

// Extract and validate emails
$extractor = new EmailExtractor();
$validator = new EmailValidator();

foreach ($testEmails as $email) {
    echo "Testing email: {$email}\n";

    // Test extraction
    $extracted = $extractor->extractEmails($email);
    echo "  Extracted: " . (count($extracted) > 0 ? implode(', ', $extracted) : 'none') . "\n";

    // Test validation directly
    $result = $validator->validate([$email]);
    if (!empty($result)) {
        $status = $result[0]['status'] === 'valid' ? '✅ valid' : '❌ invalid';
        echo "  Direct validation: {$email} - {$status}\n";
    }

    echo "\n";
}
