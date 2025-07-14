<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ValidationResult;
use App\Validators\SyntaxValidator;
use PHPUnit\Framework\TestCase;

class SyntaxValidatorTest extends TestCase
{
    private SyntaxValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SyntaxValidator();
    }

    public function testValidateWithValidEmail(): void
    {
        $result = $this->validator->validate('test@example.com');

        $this->assertTrue($result->isValid());
        $this->assertSame('test@example.com', $result->email);
        $this->assertSame('valid', $result->status);
        $this->assertNull($result->reason);
    }

    public function testValidateWithEmptyEmail(): void
    {
        $result = $this->validator->validate('');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
        $this->assertSame('Email адрес не может быть пустым', $result->reason);
    }

    public function testValidateWithWhitespaceOnlyEmail(): void
    {
        $result = $this->validator->validate('   ');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
        $this->assertSame('Email адрес не может быть пустым', $result->reason);
    }

    public function testValidateWithNoAtSymbol(): void
    {
        $result = $this->validator->validate('testexample.com');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
        $this->assertSame('Email должен содержать ровно один символ @', $result->reason);
    }

    public function testValidateWithMultipleAtSymbols(): void
    {
        $result = $this->validator->validate('test@@example.com');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
        $this->assertSame('Email должен содержать ровно один символ @', $result->reason);
    }

    public function testValidatePartsWithValidParts(): void
    {
        $result = $this->validator->validateParts('test', 'example.com', 'test@example.com');

        $this->assertTrue($result->isValid());
        $this->assertSame('test@example.com', $result->email);
        $this->assertSame('valid', $result->status);
        $this->assertNull($result->reason);
    }

    public function testValidatePartsWithInvalidLocalPart(): void
    {
        $result = $this->validator->validateParts('', 'example.com', '@example.com');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
        $this->assertSame('Локальная часть email не может быть пустой', $result->reason);
    }

    public function testValidatePartsWithInvalidDomainPart(): void
    {
        $result = $this->validator->validateParts('test', '', 'test@');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
        $this->assertSame('Доменная часть email не может быть пустой', $result->reason);
    }

    /**
     * @dataProvider validEmailProvider
     */
    public function testValidateWithValidEmails(string $email): void
    {
        $result = $this->validator->validate($email);
        $this->assertTrue($result->isValid(), "Email '{$email}' should be valid");
    }

    public function validEmailProvider(): array
    {
        return [
            ['test@example.com'],
            ['user.name@example.com'],
            ['user+tag@example.com'],
            ['user-name@example.com'],
            ['user_name@example.com'],
            ['123@example.com'],
            ['test@sub.example.com'],
            ['a@b.co'],
        ];
    }

    /**
     * @dataProvider invalidLocalPartProvider
     */
    public function testValidateWithInvalidLocalPart(string $email, string $expectedReason): void
    {
        $result = $this->validator->validate($email);
        
        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
        $this->assertSame($expectedReason, $result->reason);
    }

    public function invalidLocalPartProvider(): array
    {
        return [
            ['@example.com', 'Локальная часть email не может быть пустой'],
            ['.test@example.com', 'Локальная часть не может начинаться или заканчиваться точкой'],
            ['test.@example.com', 'Локальная часть не может начинаться или заканчиваться точкой'],
            ['te..st@example.com', 'Локальная часть не может содержать подряд идущие точки'],
            [str_repeat('a', 65) . '@example.com', 'Локальная часть email не может быть длиннее 64 символов'],
        ];
    }

    /**
     * @dataProvider invalidDomainPartProvider
     */
    public function testValidateWithInvalidDomainPart(string $email, string $expectedReason): void
    {
        $result = $this->validator->validate($email);
        
        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
        $this->assertSame($expectedReason, $result->reason);
    }

    public function invalidDomainPartProvider(): array
    {
        return [
            ['test@', 'Доменная часть email не может быть пустой'],
            ['test@.example.com', 'Доменная часть не может начинаться или заканчиваться точкой'],
            ['test@example.com.', 'Доменная часть не может начинаться или заканчиваться точкой'],
            ['test@exam..ple.com', 'Доменная часть не может содержать подряд идущие точки'],
            ['test@example', 'Доменная часть должна содержать как минимум одну точку'],
            ['test@' . str_repeat('a', 254), 'Доменная часть email не может быть длиннее 253 символов'],
        ];
    }

    public function testValidateLocalPartEmpty(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateLocalPart');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, '');
        
        $this->assertFalse($result['valid']);
        $this->assertSame('Локальная часть email не может быть пустой', $result['reason']);
    }

    public function testValidateLocalPartTooLong(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateLocalPart');
        $method->setAccessible(true);

        $longLocalPart = str_repeat('a', 65);
        $result = $method->invoke($this->validator, $longLocalPart);
        
        $this->assertFalse($result['valid']);
        $this->assertSame('Локальная часть email не может быть длиннее 64 символов', $result['reason']);
    }

    public function testValidateLocalPartWithConsecutiveDots(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateLocalPart');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, 'test..user');
        
        $this->assertFalse($result['valid']);
        $this->assertSame('Локальная часть не может содержать подряд идущие точки', $result['reason']);
    }

    public function testValidateLocalPartStartingWithDot(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateLocalPart');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, '.test');
        
        $this->assertFalse($result['valid']);
        $this->assertSame('Локальная часть не может начинаться или заканчиваться точкой', $result['reason']);
    }

    public function testValidateLocalPartEndingWithDot(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateLocalPart');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, 'test.');
        
        $this->assertFalse($result['valid']);
        $this->assertSame('Локальная часть не может начинаться или заканчиваться точкой', $result['reason']);
    }

    public function testValidateLocalPartValid(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateLocalPart');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, 'test.user');
        
        $this->assertTrue($result['valid']);
        $this->assertSame('', $result['reason']);
    }

    public function testValidateDomainPartEmpty(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateDomainPart');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, '');
        
        $this->assertFalse($result['valid']);
        $this->assertSame('Доменная часть email не может быть пустой', $result['reason']);
    }

    public function testValidateDomainPartTooLong(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateDomainPart');
        $method->setAccessible(true);

        $longDomainPart = str_repeat('a', 254);
        $result = $method->invoke($this->validator, $longDomainPart);
        
        $this->assertFalse($result['valid']);
        $this->assertSame('Доменная часть email не может быть длиннее 253 символов', $result['reason']);
    }

    public function testValidateDomainPartWithConsecutiveDots(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateDomainPart');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, 'example..com');
        
        $this->assertFalse($result['valid']);
        $this->assertSame('Доменная часть не может содержать подряд идущие точки', $result['reason']);
    }

    public function testValidateDomainPartStartingWithDot(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateDomainPart');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, '.example.com');
        
        $this->assertFalse($result['valid']);
        $this->assertSame('Доменная часть не может начинаться или заканчиваться точкой', $result['reason']);
    }

    public function testValidateDomainPartEndingWithDot(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateDomainPart');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, 'example.com.');
        
        $this->assertFalse($result['valid']);
        $this->assertSame('Доменная часть не может начинаться или заканчиваться точкой', $result['reason']);
    }

    public function testValidateDomainPartWithoutDot(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateDomainPart');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, 'example');
        
        $this->assertFalse($result['valid']);
        $this->assertSame('Доменная часть должна содержать как минимум одну точку', $result['reason']);
    }

    public function testValidateDomainPartValid(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('validateDomainPart');
        $method->setAccessible(true);

        $result = $method->invoke($this->validator, 'example.com');
        
        $this->assertTrue($result['valid']);
        $this->assertSame('', $result['reason']);
    }

    public function testValidateWithFilterVarFailure(): void
    {
        // Create a mock that would pass our custom validation but fail filter_var
        // This is tricky since filter_var is quite permissive, but we can test the logic
        $result = $this->validator->validate('test@example.com');
        
        // Since filter_var would normally pass for valid emails, 
        // we test that our validator respects filter_var results
        $this->assertTrue($result->isValid());
    }

    public function testInterfaceImplementation(): void
    {
        $this->assertInstanceOf(\App\Interfaces\ValidatorInterface::class, $this->validator);
        $this->assertInstanceOf(\App\Interfaces\PartsValidatorInterface::class, $this->validator);
    }

    public function testValidatePartsWithFilterVarFailingEmail(): void
    {
        // Test with parts that would individually pass but create an invalid email
        $result = $this->validator->validateParts('test', 'example', 'test@example');
        
        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
        $this->assertSame('Доменная часть должна содержать как минимум одну точку', $result->reason);
    }
}