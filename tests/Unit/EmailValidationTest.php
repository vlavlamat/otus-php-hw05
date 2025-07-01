<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\EmailExtractor;
use App\EmailValidator;

// List of emails from the issue description
$emailList = "valid.email1@example.com
invalid-email2example.com
good.email3@sub.domain.com
user@@doubleat.com
simple@domain
wrong_domain@.com
correct.user@site.org
hello.world@gmail.com
user123@my-mail.net
test!user@mail.com
nice.try@domain..com
user@mail@domain.com
email@domain.com
fake.email@domain,com
no_at_symbol.com
user@localhost
user@domain.c
first.last@company.biz
username@site.io
valid_email@company.co
invalid@-domain.com
bad@domain-.com
missing@tld
user@domain.toolongtld
double..dot@domain.com
underscore_in_domain@do_main.com
dot.at.end.@domain.com
.email@domain.com
user@[IPv6:2001:db8::1]
just.text";

// Expected valid emails according to the issue description
$expectedValidEmails = [
    'valid.email1@example.com',
    'good.email3@sub.domain.com',
    'correct.user@site.org',
    'hello.world@gmail.com',
    'user123@my-mail.net',
    'test!user@mail.com', // Added as per issue description
    'email@domain.com',
    'first.last@company.biz',
    'username@site.io',
    'valid_email@company.co',
    'email@domain.com', // Duplicate
    'user@[ipv6:2001:db8::1]' // Note: lowercase 'ipv6'
];

// Extract emails
$extractor = new EmailExtractor();
$emails = $extractor->extractEmails($emailList);

echo "Extracted emails: " . count($emails) . "\n";
echo "Emails with duplicates: " . implode(", ", $emails) . "\n\n";

// Validate emails
$validator = new EmailValidator();
$results = $validator->validate($emails);

echo "Validation results:\n";
$validEmails = [];
foreach ($results as $result) {
    $status = $result['status'] === 'valid' ? '✅ valid' : '❌ invalid';
    echo "{$result['email']} - {$status}\n";

    if ($result['status'] === 'valid') {
        $validEmails[] = $result['email'];
    }
}

echo "\nTotal valid emails: " . count($validEmails) . "\n";
echo "Valid emails: " . implode(", ", $validEmails) . "\n\n";

// Check if all expected valid emails are actually marked as valid
$missingValidEmails = array_diff($expectedValidEmails, $validEmails);
$unexpectedValidEmails = array_diff($validEmails, $expectedValidEmails);

if (empty($missingValidEmails) && empty($unexpectedValidEmails)) {
    echo "✅ All expected emails are correctly marked as valid!\n";
} else {
    if (!empty($missingValidEmails)) {
        echo "❌ The following emails should be valid but are marked as invalid: " . implode(", ", $missingValidEmails) . "\n";
    }
    if (!empty($unexpectedValidEmails)) {
        echo "❌ The following emails are marked as valid but should be invalid: " . implode(", ", $unexpectedValidEmails) . "\n";
    }
}
