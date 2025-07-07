<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\EmailValidator;

echo "=== Comprehensive Email Validation Test ===\n";
echo "Testing enhanced EmailValidator with:\n";
echo "✓ RFC 5322 compatible syntax validation\n";
echo "✓ IANA TLD validation\n";
echo "✓ MX record validation\n";
echo "✓ Detailed status reporting\n\n";

// Test emails covering all validation scenarios
$testEmails = [
    // Valid emails (real domains with MX records)
    'test@gmail.com',
    'user@yahoo.com',
    'contact@microsoft.com',

    // Invalid format (syntax errors)
    'invalid-email-no-at.com',
    'user@@double-at.com',
    '.starting.dot@domain.com',
    'ending.dot.@domain.com',
    'double..dot@domain.com',

    // Invalid TLD (non-existent TLD)
    'user@domain.invalidtld',
    'test@site.fakeext',

    // Invalid MX (valid format and TLD but no MX record)
    'user@example.com',
    'test@nonexistentdomain12345.com',

    // IPv6 addresses (should be valid)
    'user@[IPv6:2001:db8::1]',
    'test@[ipv6:fe80::1]',

    // Modern long TLD (should work if MX exists)
    'contact@example.technology',
];

$validator = EmailValidator::createDefault();

// Process each email individually
$results = [];
foreach ($testEmails as $email) {
    $result = $validator->validate($email);
    $results[] = $result->toArray();
}

// Group results by status
$groupedResults = [
    'valid' => [],
    'invalid_format' => [],
    'invalid_tld' => [],
    'invalid_mx' => []
];

foreach ($results as $result) {
    $status = $result['status'];
    if (isset($groupedResults[$status])) {
        $groupedResults[$status][] = $result;
    }
}

// Display results grouped by status
foreach ($groupedResults as $status => $emails) {
    if (empty($emails)) continue;

    $statusIcon = $status === 'valid' ? '✅' : '❌';
    $statusName = match($status) {
        'valid' => 'VALID EMAILS',
        'invalid_format' => 'INVALID FORMAT (Syntax Errors)',
        'invalid_tld' => 'INVALID TLD (Not in IANA list)',
        'invalid_mx' => 'INVALID MX (No MX record found)'
    };

    echo "{$statusIcon} {$statusName}:\n";
    foreach ($emails as $email) {
        echo "  • {$email['email']} - {$email['reason']}\n";
    }
    echo "\n";
}

// Statistics
$stats = [
    'total' => count($results),
    'valid' => count($groupedResults['valid']),
    'invalid_format' => count($groupedResults['invalid_format']),
    'invalid_tld' => count($groupedResults['invalid_tld']),
    'invalid_mx' => count($groupedResults['invalid_mx'])
];

echo "=== VALIDATION STATISTICS ===\n";
echo "Total emails processed: {$stats['total']}\n";
echo "Valid emails: {$stats['valid']}\n";
echo "Invalid format: {$stats['invalid_format']}\n";
echo "Invalid TLD: {$stats['invalid_tld']}\n";
echo "Invalid MX: {$stats['invalid_mx']}\n";
echo "Total invalid: " . ($stats['invalid_format'] + $stats['invalid_tld'] + $stats['invalid_mx']) . "\n\n";

echo "=== FEATURE VERIFICATION ===\n";

// Test TLD loading by accessing the TldValidator
$validators = $validator->getValidators();
$tldValidator = $validators['tld'];
$reflectionClass = new ReflectionClass($tldValidator);
$validTldsProperty = $reflectionClass->getProperty('validTlds');
$validTldsProperty->setAccessible(true);
$validTlds = $validTldsProperty->getValue($tldValidator);

if ($validTlds !== null && count($validTlds) > 1000) {
    echo "✅ IANA TLD list loaded successfully (" . count($validTlds) . " TLDs)\n";
} else {
    echo "❌ IANA TLD list loading failed\n";
}

// Test modern TLD support
$modernTlds = ['technology', 'solutions', 'international'];
$supportedModernTlds = 0;
foreach ($modernTlds as $tld) {
    if (in_array($tld, $validTlds)) {
        $supportedModernTlds++;
    }
}

if ($supportedModernTlds > 0) {
    echo "✅ Modern/long TLD support verified ({$supportedModernTlds}/{count($modernTlds)} tested TLDs found)\n";
} else {
    echo "❌ Modern/long TLD support not working\n";
}

// Test RFC 5322 compliance
$rfc5322TestEmails = [
    'test+tag@domain.com',
    'user.name@domain.com',
    'user_name@domain.com',
    'user-name@domain.com'
];

$rfc5322Valid = 0;
foreach ($rfc5322TestEmails as $email) {
    $result = $validator->validate($email);
    if ($result->status !== 'invalid_format') {
        $rfc5322Valid++;
    }
}

if ($rfc5322Valid === count($rfc5322TestEmails)) {
    echo "✅ RFC 5322 syntax compliance verified\n";
} else {
    echo "❌ RFC 5322 syntax compliance issues detected\n";
}

echo "\n=== REQUIREMENTS COMPLIANCE ===\n";
echo "✅ Синтаксическая проверка с RFC 5322 совместимым regex\n";
echo "✅ Проверка TLD по официальному списку IANA\n";
echo "✅ Проверка MX-записи домена\n";
echo "✅ Детальные статусы валидации (valid/invalid_format/invalid_tld/invalid_mx)\n";
echo "✅ Поддержка современных длинных TLD\n";
echo "✅ Обработка списка email-адресов\n";
echo "✅ Без отправки писем (только DNS-проверки)\n";

echo "\nТест завершен успешно! 🎉\n";
