<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ValidationResult;
use App\Redis\Adapters\RedisCacheAdapter;
use App\Validators\MxValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MxValidatorTest extends TestCase
{
    private MockObject|RedisCacheAdapter $mockCache;
    private MxValidator $validator;

    protected function setUp(): void
    {
        $this->mockCache = $this->createMock(RedisCacheAdapter::class);
        $this->validator = new MxValidator($this->mockCache);
    }

    public function testConstructorWithoutCache(): void
    {
        // Test constructor without cache parameter (will try to create Redis adapter)
        $validator = new MxValidator(null);
        $this->assertInstanceOf(MxValidator::class, $validator);
    }

    public function testValidateDomainWithEmptyDomain(): void
    {
        $result = $this->validator->validateDomain('', 'test@');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_mx', $result->status);
        $this->assertSame('Доменная часть email не может быть пустой', $result->reason);
    }

    public function testValidateDomainWithWhitespaceDomain(): void
    {
        $result = $this->validator->validateDomain('   ', 'test@   ');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_mx', $result->status);
        $this->assertSame('Доменная часть email не может быть пустой', $result->reason);
    }

    public function testValidateDomainWithCachedResult(): void
    {
        $domain = 'example.com';
        $email = 'test@example.com';
        $cachedData = [
            'status' => 'valid',
            'reason' => null,
            'cached_at' => time()
        ];

        $this->mockCache->expects($this->once())
            ->method('get')
            ->with($domain)
            ->willReturn($cachedData);

        $result = $this->validator->validateDomain($domain, $email);

        $this->assertTrue($result->isValid());
        $this->assertSame($email, $result->email);
        $this->assertSame('valid', $result->status);
        $this->assertNull($result->reason);
    }

    public function testValidateDomainWithInvalidCachedResult(): void
    {
        $domain = 'invalid.com';
        $email = 'test@invalid.com';
        $cachedData = [
            'status' => 'invalid_mx',
            'reason' => 'No MX record found',
            'cached_at' => time()
        ];

        $this->mockCache->method('get')->willReturn($cachedData);

        $result = $this->validator->validateDomain($domain, $email);

        $this->assertFalse($result->isValid());
        $this->assertSame($email, $result->email);
        $this->assertSame('invalid_mx', $result->status);
        $this->assertSame('No MX record found', $result->reason);
    }

    public function testValidateDomainWithIncompleteCachedData(): void
    {
        $domain = 'example.com';
        $email = 'test@example.com';
        $incompleteCachedData = ['status' => 'valid']; // Missing 'reason'

        $this->mockCache->method('get')->willReturn($incompleteCachedData);
        $this->mockCache->method('set')->willReturn(true);

        // Since cached data is incomplete, it should perform full validation
        // We need to mock the domain format validation
        $result = $this->validator->validateDomain($domain, $email);

        $this->assertInstanceOf(ValidationResult::class, $result);
    }

    public function testValidateWithEmptyEmail(): void
    {
        $result = $this->validator->validate('');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_mx', $result->status);
        $this->assertSame('Email адрес не может быть пустым', $result->reason);
    }

    public function testValidateWithWhitespaceEmail(): void
    {
        $result = $this->validator->validate('   ');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_mx', $result->status);
        $this->assertSame('Email адрес не может быть пустым', $result->reason);
    }

    public function testValidateWithNoAtSymbol(): void
    {
        $result = $this->validator->validate('testexample.com');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_mx', $result->status);
        $this->assertSame('Email должен содержать ровно один символ @', $result->reason);
    }

    public function testValidateWithMultipleAtSymbols(): void
    {
        $result = $this->validator->validate('test@@example.com');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_mx', $result->status);
        $this->assertSame('Email должен содержать ровно один символ @', $result->reason);
    }

    public function testGetCachedResultWithNullCache(): void
    {
        $this->mockCache->method('get')->willReturn(null);

        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('getCachedResult');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, 'example.com', 'test@example.com');
        $this->assertNull($result);
    }

    public function testGetCachedResultWithException(): void
    {
        $this->mockCache->method('get')->willThrowException(new \Exception('Redis error'));

        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('getCachedResult');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, 'example.com', 'test@example.com');
        $this->assertNull($result);
    }

    public function testCacheResult(): void
    {
        $this->mockCache->method('set')->willReturn(true);

        $validationResult = ValidationResult::valid('test@example.com');

        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('cacheResult');
        $method->setAccessible(true);

        // Should not throw any exceptions
        $method->invoke($this->validator, 'example.com', $validationResult);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testCacheResultWithException(): void
    {
        $this->mockCache->method('set')->willThrowException(new \Exception('Redis error'));

        $validationResult = ValidationResult::valid('test@example.com');

        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('cacheResult');
        $method->setAccessible(true);

        // Should not throw any exceptions (errors are logged)
        $method->invoke($this->validator, 'example.com', $validationResult);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testIsValidDomainFormatWithValidDomains(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('isValidDomainFormat');
        $method->setAccessible(true);

        $validDomains = [
            'example.com',
            'sub.example.com',
            'test-domain.org',
            'a.b',
            '123.456.com',
        ];

        foreach ($validDomains as $domain) {
            $result = $method->invoke($this->validator, $domain);
            $this->assertTrue($result, "Domain '{$domain}' should be valid");
        }
    }

    public function testIsValidDomainFormatWithInvalidDomains(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('isValidDomainFormat');
        $method->setAccessible(true);

        $invalidDomains = [
            str_repeat('a', 254), // Too long
            'domain with spaces.com',
            '.example.com', // Starts with dot
            'example.com.', // Ends with dot
            'exam..ple.com', // Double dots
            'exam--ple.com', // Double dashes
            'domain@invalid.com', // Contains @
        ];

        foreach ($invalidDomains as $domain) {
            $result = $method->invoke($this->validator, $domain);
            $this->assertFalse($result, "Domain '{$domain}' should be invalid");
        }
    }

    public function testPerformFullValidationWithInvalidFormat(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('performFullValidation');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, 'invalid..domain.com', 'test@invalid..domain.com');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_mx', $result->status);
        $this->assertStringContainsString('Неверный формат доменной части', $result->reason);
    }

    public function testInterfaceImplementation(): void
    {
        $this->assertInstanceOf(\App\Interfaces\ValidatorInterface::class, $this->validator);
        $this->assertInstanceOf(\App\Interfaces\DomainValidatorInterface::class, $this->validator);
    }

    public function testValidatorWithoutCache(): void
    {
        $validator = new MxValidator(null);

        // Test that it works without cache
        $result = $validator->validateDomain('invalid..domain.com', 'test@invalid..domain.com');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_mx', $result->status);
    }

    /**
     * @dataProvider domainFormatProvider
     */
    public function testDomainFormatValidation(string $domain, bool $expectedValid, string $description): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('isValidDomainFormat');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, $domain);
        $this->assertSame($expectedValid, $result, $description);
    }

    public function domainFormatProvider(): array
    {
        return [
            // Valid domains
            ['example.com', true, 'Simple domain should be valid'],
            ['sub.example.com', true, 'Subdomain should be valid'],
            ['test-domain.org', true, 'Domain with dash should be valid'],
            ['123.example.com', true, 'Domain with numbers should be valid'],
            ['a.b', true, 'Short domain should be valid'],

            // Invalid domains
            [str_repeat('a', 254), false, 'Domain longer than 253 chars should be invalid'],
            ['domain with spaces.com', false, 'Domain with spaces should be invalid'],
            ['.example.com', false, 'Domain starting with dot should be invalid'],
            ['example.com.', false, 'Domain ending with dot should be invalid'],
            ['exam..ple.com', false, 'Domain with consecutive dots should be invalid'],
            ['exam--ple.com', false, 'Domain with consecutive dashes should be invalid'],
            ['domain@test.com', false, 'Domain with @ symbol should be invalid'],
            ['domain#test.com', false, 'Domain with # symbol should be invalid'],
        ];
    }

    public function testCacheIntegration(): void
    {
        $domain = 'example.com';
        $email = 'test@example.com';

        // First call - cache miss, should perform validation and cache result
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with($domain)
            ->willReturn(null);

        $this->mockCache->expects($this->once())
            ->method('set')
            ->with($domain, $this->anything(), 7200); // CACHE_TTL = 7200

        $result1 = $this->validator->validateDomain($domain, $email);
        $this->assertInstanceOf(ValidationResult::class, $result1);
    }

    public function testDomainNormalization(): void
    {
        $domain = '  EXAMPLE.COM  ';
        $email = 'test@  EXAMPLE.COM  ';

        $this->mockCache->method('get')->willReturn(null);
        $this->mockCache->method('set')->willReturn(true);

        // The domain should be normalized (trimmed and lowercased)
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with('example.com'); // Should be normalized

        $result = $this->validator->validateDomain($domain, $email);
        $this->assertInstanceOf(ValidationResult::class, $result);
    }

    public function testValidateEmailExtractsDomainCorrectly(): void
    {
        $email = 'user@EXAMPLE.COM';

        $this->mockCache->method('get')->willReturn(null);
        $this->mockCache->method('set')->willReturn(true);

        // Should extract and normalize domain
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with('example.com');

        $result = $this->validator->validate($email);
        $this->assertInstanceOf(ValidationResult::class, $result);
    }

    public function testErrorHandlingInCacheOperations(): void
    {
        $domain = 'example.com';
        $email = 'test@example.com';

        // Test cache get error
        $this->mockCache->method('get')->willThrowException(new \Exception('Cache get error'));
        $this->mockCache->method('set')->willReturn(true);

        $result = $this->validator->validateDomain($domain, $email);
        $this->assertInstanceOf(ValidationResult::class, $result);

        // Test cache set error
        $this->mockCache = $this->createMock(RedisCacheAdapter::class);
        $this->validator = new MxValidator($this->mockCache);

        $this->mockCache->method('get')->willReturn(null);
        $this->mockCache->method('set')->willThrowException(new \Exception('Cache set error'));

        $result = $this->validator->validateDomain($domain, $email);
        $this->assertInstanceOf(ValidationResult::class, $result);
    }
}
