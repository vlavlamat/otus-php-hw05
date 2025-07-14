<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ValidationResult;
use PHPUnit\Framework\TestCase;

class ValidationResultTest extends TestCase
{
    public function testConstructorWithValidResult(): void
    {
        $email = 'test@example.com';
        $status = 'valid';
        $reason = null;

        $result = new ValidationResult($email, $status, $reason);

        $this->assertSame($email, $result->email);
        $this->assertSame($status, $result->status);
        $this->assertNull($result->reason);
    }

    public function testConstructorWithInvalidResult(): void
    {
        $email = 'invalid@example.com';
        $status = 'invalid_format';
        $reason = 'Invalid email format';

        $result = new ValidationResult($email, $status, $reason);

        $this->assertSame($email, $result->email);
        $this->assertSame($status, $result->status);
        $this->assertSame($reason, $result->reason);
    }

    public function testValidStaticMethod(): void
    {
        $email = 'valid@example.com';
        $result = ValidationResult::valid($email);

        $this->assertSame($email, $result->email);
        $this->assertSame('valid', $result->status);
        $this->assertNull($result->reason);
        $this->assertTrue($result->isValid());
    }

    public function testInvalidFormatStaticMethod(): void
    {
        $email = 'invalid@example.com';
        $reason = 'Missing @ symbol';
        $result = ValidationResult::invalidFormat($email, $reason);

        $this->assertSame($email, $result->email);
        $this->assertSame('invalid_format', $result->status);
        $this->assertSame($reason, $result->reason);
        $this->assertFalse($result->isValid());
    }

    public function testInvalidTldStaticMethod(): void
    {
        $email = 'test@example.invalidtld';
        $reason = 'TLD not found in IANA list';
        $result = ValidationResult::invalidTld($email, $reason);

        $this->assertSame($email, $result->email);
        $this->assertSame('invalid_tld', $result->status);
        $this->assertSame($reason, $result->reason);
        $this->assertFalse($result->isValid());
    }

    public function testInvalidMxStaticMethod(): void
    {
        $email = 'test@nonexistent.com';
        $reason = 'No MX record found';
        $result = ValidationResult::invalidMx($email, $reason);

        $this->assertSame($email, $result->email);
        $this->assertSame('invalid_mx', $result->status);
        $this->assertSame($reason, $result->reason);
        $this->assertFalse($result->isValid());
    }

    public function testIsValidReturnsTrueForValidStatus(): void
    {
        $result = ValidationResult::valid('test@example.com');
        $this->assertTrue($result->isValid());
    }

    public function testIsValidReturnsFalseForInvalidStatuses(): void
    {
        $invalidStatuses = [
            ValidationResult::invalidFormat('test@example.com', 'Invalid format'),
            ValidationResult::invalidTld('test@example.com', 'Invalid TLD'),
            ValidationResult::invalidMx('test@example.com', 'No MX record'),
        ];

        foreach ($invalidStatuses as $result) {
            $this->assertFalse($result->isValid());
        }
    }

    public function testToArrayWithValidResult(): void
    {
        $email = 'test@example.com';
        $result = ValidationResult::valid($email);
        $array = $result->toArray();

        $expected = [
            'email' => $email,
            'status' => 'valid',
            'reason' => null,
        ];

        $this->assertSame($expected, $array);
    }

    public function testToArrayWithInvalidResult(): void
    {
        $email = 'invalid@example.com';
        $reason = 'Invalid format';
        $result = ValidationResult::invalidFormat($email, $reason);
        $array = $result->toArray();

        $expected = [
            'email' => $email,
            'status' => 'invalid_format',
            'reason' => $reason,
        ];

        $this->assertSame($expected, $array);
    }

    public function testReadonlyProperties(): void
    {
        $result = ValidationResult::valid('test@example.com');
        
        // Test that properties are readonly by checking they exist and are accessible
        $this->assertIsString($result->email);
        $this->assertIsString($result->status);
        $this->assertNull($result->reason);
    }

    /**
     * @dataProvider validationStatusProvider
     */
    public function testAllValidationStatuses(string $email, string $status, ?string $reason, bool $expectedValid): void
    {
        $result = new ValidationResult($email, $status, $reason);
        
        $this->assertSame($email, $result->email);
        $this->assertSame($status, $result->status);
        $this->assertSame($reason, $result->reason);
        $this->assertSame($expectedValid, $result->isValid());
    }

    public function validationStatusProvider(): array
    {
        return [
            'valid status' => ['test@example.com', 'valid', null, true],
            'invalid_format status' => ['invalid', 'invalid_format', 'Missing @ symbol', false],
            'invalid_tld status' => ['test@example.fake', 'invalid_tld', 'TLD not in IANA list', false],
            'invalid_mx status' => ['test@nonexistent.com', 'invalid_mx', 'No MX record', false],
        ];
    }

    public function testJsonSerialization(): void
    {
        $result = ValidationResult::invalidFormat('test@invalid', 'Missing domain');
        $json = json_encode($result->toArray());
        $decoded = json_decode($json, true);

        $this->assertSame('test@invalid', $decoded['email']);
        $this->assertSame('invalid_format', $decoded['status']);
        $this->assertSame('Missing domain', $decoded['reason']);
    }
}