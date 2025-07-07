<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\EmailExtractor;
use App\EmailValidator;

// Test emails that were previously valid but now failing
$testEmails = [
    'valid.email1@example.com',
    'correct.user@site.org',
    'first.last@company.biz',
    'hello.world@gmail.com',
    'user@[ipv6:2001:db8::1]'
];

echo "=== Detailed Email Validation Test ===\n\n";

$validator = new EmailValidator();
$results = $validator->validate($testEmails);

foreach ($results as $result) {
    echo "Email: {$result['email']}\n";
    echo "Status: {$result['status']}\n";
    echo "Reason: {$result['reason']}\n";
    echo "---\n";
}

echo "\nTesting individual components:\n\n";

// Test TLD loading
echo "Testing TLD loading...\n";
$reflectionClass = new ReflectionClass($validator);
$loadTldsMethod = $reflectionClass->getMethod('loadValidTlds');
$loadTldsMethod->setAccessible(true);
$loadTldsMethod->invoke($validator);

$validTldsProperty = $reflectionClass->getProperty('validTlds');
$validTldsProperty->setAccessible(true);
$validTlds = $validTldsProperty->getValue();

if ($validTlds !== null) {
    echo "TLD list loaded successfully. Count: " . count($validTlds) . "\n";
    echo "Sample TLDs: " . implode(', ', array_slice($validTlds, 0, 10)) . "...\n";
    
    // Check if common TLDs are present
    $commonTlds = ['com', 'org', 'net', 'biz'];
    foreach ($commonTlds as $tld) {
        $present = in_array($tld, $validTlds) ? '✅' : '❌';
        echo "TLD '{$tld}': {$present}\n";
    }
} else {
    echo "❌ Failed to load TLD list\n";
}

echo "\n";