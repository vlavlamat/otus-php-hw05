<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Redis\Adapters\RedisCacheAdapter;
use App\Validators\TldValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

class TldValidatorTest extends TestCase
{
    private MockObject|RedisCacheAdapter $mockCache;
    private TldValidator $validator;

    protected function setUp(): void
    {
        $this->mockCache = $this->createMock(RedisCacheAdapter::class);
        $this->validator = new TldValidator($this->mockCache);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testConstructorWithoutCache(): void
    {
        // Test that the constructor does not throw an exception when the cache is not provided.
        new TldValidator(null);

    }

    public function testValidateDomainWithValidTld(): void
    {
        // Mock cache to return fallback TLDs
        $this->mockCache->method('exists')->willReturn(false);

        $result = $this->validator->validateDomain('example.com', 'test@example.com');

        $this->assertTrue($result->isValid());
        $this->assertSame('test@example.com', $result->email);
        $this->assertSame('valid', $result->status);
        $this->assertNull($result->reason);
    }

    public function testValidateDomainWithInvalidTld(): void
    {
        // Mock cache to return fallback TLDs
        $this->mockCache->method('exists')->willReturn(false);

        $result = $this->validator->validateDomain('example.invalidtld', 'test@example.invalidtld');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_tld', $result->status);
        $this->assertStringContainsString('INVALIDTLD', $result->reason);
        $this->assertStringContainsString('не найден в списке официальных доменов IANA', $result->reason);
    }

    public function testValidateDomainWithEmptyDomain(): void
    {
        $result = $this->validator->validateDomain('', 'test@');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_tld', $result->status);
        $this->assertSame('Доменная часть email не может быть пустой', $result->reason);
    }

    public function testValidateDomainWithWhitespaceDomain(): void
    {
        $result = $this->validator->validateDomain('   ', 'test@   ');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_tld', $result->status);
        $this->assertSame('Доменная часть email не может быть пустой', $result->reason);
    }

    public function testValidateDomainWithoutDot(): void
    {
        // Mock cache to return fallback TLDs
        $this->mockCache->method('exists')->willReturn(false);

        $result = $this->validator->validateDomain('example', 'test@example');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_tld', $result->status);
        // The actual behavior is that it extracts 'EXAMPLE' as TLD and checks if it's valid
        $this->assertStringContainsString('EXAMPLE', $result->reason);
        $this->assertStringContainsString('не найден в списке официальных доменов IANA', $result->reason);
    }

    public function testValidateWithValidEmail(): void
    {
        // Mock cache to return fallback TLDs
        $this->mockCache->method('exists')->willReturn(false);

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
        $this->assertSame('invalid_tld', $result->status);
        $this->assertSame('Email адрес не может быть пустым', $result->reason);
    }

    public function testValidateWithWhitespaceEmail(): void
    {
        $result = $this->validator->validate('   ');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_tld', $result->status);
        $this->assertSame('Email адрес не может быть пустым', $result->reason);
    }

    public function testValidateWithNoAtSymbol(): void
    {
        $result = $this->validator->validate('testexample.com');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_tld', $result->status);
        $this->assertSame('Email должен содержать ровно один символ @', $result->reason);
    }

    public function testValidateWithMultipleAtSymbols(): void
    {
        $result = $this->validator->validate('test@@example.com');

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_tld', $result->status);
        $this->assertSame('Email должен содержать ровно один символ @', $result->reason);
    }

    /**
     * @throws ReflectionException
     */
    public function testExtractTld(): void
    {
        $reflection = new ReflectionClass($this->validator);
        $method = $reflection->getMethod('extractTld');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $method->setAccessible(true);

        $this->assertSame('COM', $method->invoke($this->validator, 'example.com'));
        $this->assertSame('ORG', $method->invoke($this->validator, 'sub.example.org'));
        $this->assertSame('UK', $method->invoke($this->validator, 'example.co.uk')); // Extracts last part
        $this->assertSame('EXAMPLE', $method->invoke($this->validator, 'example')); // No dot, returns whole string
        $this->assertSame('', $method->invoke($this->validator, ''));
    }

    /**
     * @throws ReflectionException
     */
    public function testIsTldValidWithFallbackTlds(): void
    {
        // Mock cache to return false so fallback TLDs are used
        $this->mockCache->method('exists')->willReturn(false);

        $reflection = new ReflectionClass($this->validator);
        $method = $reflection->getMethod('isTldValid');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $method->setAccessible(true);

        // Test valid TLDs from fallback list
        $this->assertTrue($method->invoke($this->validator, 'COM'));
        $this->assertTrue($method->invoke($this->validator, 'com')); // Case insensitive
        $this->assertTrue($method->invoke($this->validator, 'ORG'));
        $this->assertTrue($method->invoke($this->validator, 'RU'));

        // Test invalid TLD
        $this->assertFalse($method->invoke($this->validator, 'INVALIDTLD'));
    }

    /**
     * @throws ReflectionException
     */
    public function testLoadFromRedisCacheSuccess(): void
    {
        $cachedTlds = ['COM', 'NET', 'ORG', 'TEST'];
        $metadata = [
            'loaded_at' => time(),
            'version' => '3.0',
            'source' => 'IANA',
            'count' => 4
        ];

        $this->mockCache->method('exists')->willReturn(true);
        $this->mockCache->method('get')
            ->willReturnMap([
                ['tlds_list', $cachedTlds],
                ['tlds_metadata', $metadata]
            ]);

        $reflection = new ReflectionClass($this->validator);
        $method = $reflection->getMethod('loadFromRedisCache');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $method->setAccessible(true);

        $result = $method->invoke($this->validator);
        $this->assertTrue($result);

        // Check that TLDs were loaded
        $validTldsProperty = $reflection->getProperty('validTlds');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $validTldsProperty->setAccessible(true);
        $this->assertSame($cachedTlds, $validTldsProperty->getValue($this->validator));
    }

    /**
     * @throws ReflectionException
     */
    public function testLoadFromRedisCacheFailure(): void
    {
        $this->mockCache->method('exists')->willReturn(false);

        $reflection = new ReflectionClass($this->validator);
        $method = $reflection->getMethod('loadFromRedisCache');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $method->setAccessible(true);

        $result = $method->invoke($this->validator);
        $this->assertFalse($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testLoadFromRedisCacheWithInvalidData(): void
    {
        $this->mockCache->method('exists')->willReturn(true);
        $this->mockCache->method('get')->willReturn(null); // Invalid data

        $reflection = new ReflectionClass($this->validator);
        $method = $reflection->getMethod('loadFromRedisCache');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $method->setAccessible(true);

        $result = $method->invoke($this->validator);
        $this->assertFalse($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testLoadFromRedisCacheWithEmptyArray(): void
    {
        $this->mockCache->method('exists')->willReturn(true);
        $this->mockCache->method('get')->willReturn([]); // Empty array

        $reflection = new ReflectionClass($this->validator);
        $method = $reflection->getMethod('loadFromRedisCache');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $method->setAccessible(true);

        $result = $method->invoke($this->validator);
        $this->assertFalse($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testSaveToRedisCache(): void
    {
        $this->mockCache->method('set')->willReturn(true);

        // Set some TLDs first
        $reflection = new ReflectionClass($this->validator);
        $validTldsProperty = $reflection->getProperty('validTlds');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $validTldsProperty->setAccessible(true);
        $validTldsProperty->setValue($this->validator, ['COM', 'NET', 'ORG']);

        $method = $reflection->getMethod('saveToRedisCache');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $method->setAccessible(true);

        // Should not throw any exceptions
        $method->invoke($this->validator);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * @throws ReflectionException
     */
    public function testSaveToRedisCacheWithoutCache(): void
    {
        $validator = new TldValidator(null);

        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('saveToRedisCache');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $method->setAccessible(true);

        // Should not throw any exceptions
        $method->invoke($validator);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * @throws ReflectionException
     */
    public function testLoadFallbackTlds(): void
    {
        $reflection = new ReflectionClass($this->validator);
        $method = $reflection->getMethod('loadFallbackTlds');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $method->setAccessible(true);

        $method->invoke($this->validator);

        $validTldsProperty = $reflection->getProperty('validTlds');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $validTldsProperty->setAccessible(true);
        $tlds = $validTldsProperty->getValue($this->validator);

        $this->assertIsArray($tlds);
        $this->assertNotEmpty($tlds);
        $this->assertContains('COM', $tlds);
        $this->assertContains('NET', $tlds);
        $this->assertContains('ORG', $tlds);
        $this->assertContains('RU', $tlds);
    }

    public function testClearCacheSuccess(): void
    {
        $this->mockCache->method('delete')->willReturn(true);

        $result = $this->validator->clearCache();
        $this->assertTrue($result);
    }

    public function testGetCacheInfoWithoutCache(): void
    {
        // Создаем TldValidator с мок-объектом, который имитирует недоступный Redis
        $mockCache = $this->createMock(RedisCacheAdapter::class);
        $mockCache->method('exists')->willReturn(false);
        $mockCache->method('get')->willReturn(null);
        $mockCache->method('getTtl')->willReturn(-2);

        $validator = new TldValidator($mockCache);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('getCacheInfo');
        $method->setAccessible(true);

        // Вызываем метод getCacheInfo и проверяем fallback поведение
        $result = $method->invoke($validator);

        // Проверяем структуру ответа согласно реальной реализации
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('ttl_seconds', $result);
        $this->assertArrayHasKey('ttl_human', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('current_tlds_count', $result);

        // Проверяем значения для случая без кэша
        $this->assertEquals('not_cached', $result['status']);
        $this->assertEquals(-2, $result['ttl_seconds']);
        $this->assertEquals('expired', $result['ttl_human']);
        $this->assertNull($result['metadata']);
        $this->assertIsInt($result['current_tlds_count']);
        $this->assertGreaterThan(0, $result['current_tlds_count']); // Должен использовать fallback TLDs
    }

    public function testClearCacheWithoutCache(): void
    {
        // Создаем TldValidator с мок-объектом, который имитирует недоступный Redis
        $mockCache = $this->createMock(RedisCacheAdapter::class);
        $mockCache->method('exists')->willReturn(false);
        $mockCache->method('delete')->willReturn(false);

        $validator = new TldValidator($mockCache);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('clearCache');
        $method->setAccessible(true);

        // Вызываем метод clearCache и проверяем, что он работает с недоступным кэшем
        $result = $method->invoke($validator);
        $this->assertFalse($result); // Должен вернуть false когда кэш недоступен
    }

    public function testClearCacheFailure(): void
    {
        $this->mockCache->method('delete')->willReturn(false);

        $result = $this->validator->clearCache();
        $this->assertFalse($result);
    }

    public function testGetCacheInfoWithCache(): void
    {
        $metadata = ['loaded_at' => time(), 'version' => '3.0'];

        $this->mockCache->method('get')->willReturn($metadata);
        $this->mockCache->method('getTtl')->willReturn(3600);
        $this->mockCache->method('exists')->willReturn(true);

        $info = $this->validator->getCacheInfo();

        $this->assertSame('cached', $info['status']);
        $this->assertSame(3600, $info['ttl_seconds']);
        $this->assertSame('01:00:00', $info['ttl_human']);
        $this->assertSame($metadata, $info['metadata']);
        $this->assertIsInt($info['current_tlds_count']);
    }

    public function testGetCacheInfoWithExpiredCache(): void
    {
        $this->mockCache->method('get')->willReturn(null);
        $this->mockCache->method('getTtl')->willReturn(-1);
        $this->mockCache->method('exists')->willReturn(false);

        $info = $this->validator->getCacheInfo();

        $this->assertSame('not_cached', $info['status']);
        $this->assertSame(-1, $info['ttl_seconds']);
        $this->assertSame('expired', $info['ttl_human']);
        $this->assertNull($info['metadata']);
        $this->assertIsInt($info['current_tlds_count']);
    }

    /**
     * @dataProvider validTldProvider
     */
    public function testValidateWithVariousTlds(string $email, bool $expectedValid): void
    {
        // Mock cache to return false so fallback TLDs are used
        $this->mockCache->method('exists')->willReturn(false);

        $result = $this->validator->validate($email);
        $this->assertSame($expectedValid, $result->isValid(), "Email '$email' validation failed");
    }

    public function validTldProvider(): array
    {
        return [
            // Valid TLDs from fallback list
            ['test@example.com', true],
            ['test@example.net', true],
            ['test@example.org', true],
            ['test@example.ru', true],
            ['test@example.de', true],
            ['test@example.tech', true],

            // Invalid TLDs
            ['test@example.invalidtld', false],
            ['test@example.fake', false],
            ['test@example.notreal', false],
        ];
    }

    public function testCaseInsensitiveTldValidation(): void
    {
        // Mock cache to return false so fallback TLDs are used
        $this->mockCache->method('exists')->willReturn(false);

        $testCases = [
            'test@example.COM',
            'test@example.com',
            'test@example.Com',
            'test@example.cOm',
        ];

        foreach ($testCases as $email) {
            $result = $this->validator->validate($email);
            $this->assertTrue($result->isValid(), "Email '$email' should be valid regardless of TLD case");
        }
    }

    public function testComplexDomainStructures(): void
    {
        // Mock cache to return false so fallback TLDs are used
        $this->mockCache->method('exists')->willReturn(false);

        $testCases = [
            ['test@sub.example.com', true],
            ['test@deep.sub.example.com', true],
            ['test@very.deep.sub.example.com', true],
            ['test@sub.example.invalidtld', false],
        ];

        foreach ($testCases as [$email, $expectedValid]) {
            $result = $this->validator->validate($email);
            $this->assertSame($expectedValid, $result->isValid(), "Email '$email' validation failed");
        }
    }
}