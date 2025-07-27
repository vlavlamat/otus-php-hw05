<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Bootstrap\EnvironmentLoader;
use RuntimeException;

/**
 * Тесты для класса EnvironmentLoader
 *
 * Проверяет корректность валидации переменных окружения
 */
class EnvironmentLoaderTest extends TestCase
{
    /**
     * Сохраняем оригинальные значения переменных окружения
     * для восстановления после тестов
     */
    private array $originalEnvVars = [];

    /**
     * Список обязательных переменных окружения (копия из EnvironmentLoader)
     */
    private const REQUIRED_ENV_VARIABLES = [
        'REDIS_QUORUM',
        'REDIS_TIMEOUT',
        'REDIS_READ_TIMEOUT',
        'REDIS_CLUSTER_NODES',
        'REDIS_SESSION_PREFIX',
        'REDIS_SESSION_LIFETIME',
        'REDIS_GC_PROBABILITY',
        'REDIS_GC_DIVISOR',
        'REDIS_TLD_CACHE_TTL',
        'REDIS_TLD_CACHE_PREFIX',
        'REDIS_MX_CACHE_TTL',
        'REDIS_MX_CACHE_PREFIX',
        'REDIS_CHECK_INTERVAL',
        'REDIS_PING_TIMEOUT',
        'APP_ENV',
        'APP_DEBUG'
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Сохраняем оригинальные значения переменных окружения
        foreach (self::REQUIRED_ENV_VARIABLES as $variable) {
            $this->originalEnvVars[$variable] = getenv($variable);
        }
    }

    protected function tearDown(): void
    {
        // Восстанавливаем оригинальные значения переменных окружения
        foreach ($this->originalEnvVars as $variable => $value) {
            if ($value === false) {
                putenv($variable);
            } else {
                putenv("$variable=$value");
            }
        }

        parent::tearDown();
    }

    /**
     * Тест успешной загрузки с корректными переменными окружения
     */
    public function testLoadWithValidEnvironmentVariables(): void
    {
        // Устанавливаем все необходимые переменные окружения
        $this->setValidEnvironmentVariables();

        // Проверяем, что метод load() не выбрасывает исключение
        $this->expectNotToPerformAssertions();
        EnvironmentLoader::load();
    }

    /**
     * Тест с отсутствующими переменными окружения
     */
    public function testLoadWithMissingEnvironmentVariables(): void
    {
        // Удаляем несколько переменных окружения
        putenv('REDIS_QUORUM');
        putenv('APP_ENV');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ошибки переменных окружения');

        try {
            EnvironmentLoader::load();
        } catch (RuntimeException $e) {
            // Проверяем, что в сообщении есть информация об отсутствующих переменных
            $this->assertStringContainsString('Отсутствуют переменные окружения', $e->getMessage());
            $this->assertStringContainsString('REDIS_QUORUM', $e->getMessage());
            $this->assertStringContainsString('APP_ENV', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Тест с пустыми переменными окружения
     */
    public function testLoadWithEmptyEnvironmentVariables(): void
    {
        // Устанавливаем все переменные, но некоторые делаем пустыми
        $this->setValidEnvironmentVariables();
        putenv('REDIS_TIMEOUT=');
        putenv('APP_DEBUG=   '); // Пробелы должны считаться пустым значением

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ошибки переменных окружения');
        $this->expectExceptionMessage('Пустые переменные окружения');

        EnvironmentLoader::load();
    }

    /**
     * Тест с некорректным значением APP_ENV
     */
    public function testLoadWithInvalidAppEnvironment(): void
    {
        // Устанавливаем все переменные корректно, кроме APP_ENV
        $this->setValidEnvironmentVariables();
        putenv('APP_ENV=invalid_environment');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Недопустимое значение APP_ENV: 'invalid_environment'");
        $this->expectExceptionMessage('Допустимые значения: development, production');

        EnvironmentLoader::load();
    }

    /**
     * Тест с корректным значением APP_ENV = development
     */
    public function testLoadWithDevelopmentEnvironment(): void
    {
        $this->setValidEnvironmentVariables();
        putenv('APP_ENV=development');

        $this->expectNotToPerformAssertions();
        EnvironmentLoader::load();
    }

    /**
     * Тест с корректным значением APP_ENV = production
     */
    public function testLoadWithProductionEnvironment(): void
    {
        $this->setValidEnvironmentVariables();
        putenv('APP_ENV=production');

        $this->expectNotToPerformAssertions();
        EnvironmentLoader::load();
    }

    /**
     * Тест комбинированных ошибок (отсутствующие и пустые переменные)
     */
    public function testLoadWithCombinedErrors(): void
    {
        // Удаляем одну переменную и делаем другую пустой
        putenv('REDIS_QUORUM');
        putenv('REDIS_TIMEOUT=');
        putenv('APP_ENV=development'); // Эта должна быть корректной

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ошибки переменных окружения');
        $this->expectExceptionMessage('Отсутствуют переменные окружения: REDIS_QUORUM');
        $this->expectExceptionMessage('Пустые переменные окружения: REDIS_TIMEOUT');

        EnvironmentLoader::load();
    }

    /**
     * Вспомогательный метод для установки корректных переменных окружения
     */
    private function setValidEnvironmentVariables(): void
    {
        putenv('REDIS_QUORUM=2');
        putenv('REDIS_TIMEOUT=5');
        putenv('REDIS_READ_TIMEOUT=5');
        putenv('REDIS_CLUSTER_NODES=redis-node1:7001,redis-node2:7002');
        putenv('REDIS_SESSION_PREFIX=sess_');
        putenv('REDIS_SESSION_LIFETIME=1440');
        putenv('REDIS_GC_PROBABILITY=1');
        putenv('REDIS_GC_DIVISOR=100');
        putenv('REDIS_TLD_CACHE_TTL=86400');
        putenv('REDIS_TLD_CACHE_PREFIX=tld_');
        putenv('REDIS_MX_CACHE_TTL=3600');
        putenv('REDIS_MX_CACHE_PREFIX=mx_');
        putenv('REDIS_CHECK_INTERVAL=30');
        putenv('REDIS_PING_TIMEOUT=2');
        putenv('APP_ENV=development');
        putenv('APP_DEBUG=true');
    }
}