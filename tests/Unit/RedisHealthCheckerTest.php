<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Redis\Health\RedisHealthChecker;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RedisCluster;
use ReflectionClass;
use ReflectionException;

/**
 * @covers \App\Redis\Health\RedisHealthChecker
 */
class RedisHealthCheckerTest extends TestCase
{
    private array $defaultConfig;
    private MockObject|RedisCluster $redisClusterMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultConfig = [
            'cluster' => [
                'nodes' => ['redis1:6379', 'redis2:6379', 'redis3:6379'],
                'quorum' => 2,
            ],
        ];
        $this->redisClusterMock = $this->createMock(RedisCluster::class);
    }

    /**
     * Создает экземпляр RedisHealthChecker с моком RedisCluster.
     * Конструктор оригинального класса не вызывается, чтобы избежать реального подключения.
     * @throws ReflectionException
     */
    private function createChecker(): RedisHealthChecker
    {
        $checker = (new ReflectionClass(RedisHealthChecker::class))->newInstanceWithoutConstructor();

        $reflection = new ReflectionClass($checker);

        $clusterProperty = $reflection->getProperty('cluster');
        $clusterProperty->setValue($checker, $this->redisClusterMock);

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($checker, $this->defaultConfig);

        return $checker;
    }

    /**
     * @throws ReflectionException
     */
    public function testGetRequiredQuorum(): void
    {
        $checker = $this->createChecker();
        $this->assertSame(2, $checker->getRequiredQuorum());
    }

    /**
     * @throws ReflectionException
     */
    public function testGetClusterStatusReturnsCorrectStatuses(): void
    {
        $checker = $this->createChecker();

        // Настраиваем поведение мока RedisCluster для разных узлов.
        // Используем willReturnCallback вместо устаревшего withConsecutive.
        $this->redisClusterMock->method('ping')
            ->willReturnCallback(function (string $node) {
                return match ($node) {
                    'redis1:6379' => true, // Успешный пинг
                    'redis2:6379' => throw new Exception('Connection failed'), // Ошибка пинга
                    'redis3:6379' => false, // Неудачный пинг (ответ не 'PONG')
                };
            });

        $expected = [
            'redis1:6379' => 'connected',
            'redis2:6379' => 'error: Connection failed',
            'redis3:6379' => 'disconnected',
        ];

        $this->assertEquals($expected, $checker->getClusterStatus());
    }

    /**
     * @throws ReflectionException
     */
    public function testIsConnectedReturnsTrueWhenQuorumIsMet(): void
    {
        $checker = $this->createChecker();

        // Настраиваем мок RedisCluster для возврата результата, где кворум достигнут
        $this->redisClusterMock->method('ping')
            ->willReturnCallback(function (string $node) {
                return match ($node) {
                    'redis1:6379' => true,  // connected
                    'redis2:6379' => true,  // connected  
                    'redis3:6379' => false, // disconnected
                };
            });

        // Кворум = 2, connected = 2, поэтому должен вернуть true
        $this->assertTrue($checker->isConnected());
    }

    /**
     * @throws ReflectionException
     */
    public function testIsConnectedReturnsFalseWhenQuorumIsNotMet(): void
    {
        $checker = $this->createChecker();

        // Настраиваем мок RedisCluster для возврата результата, где кворум НЕ достигнут
        $this->redisClusterMock->method('ping')
            ->willReturnCallback(function (string $node) {
                return match ($node) {
                    'redis1:6379' => true,  // connected
                    'redis2:6379' => false, // disconnected
                    'redis3:6379' => throw new Exception('Connection failed'), // error
                };
            });

        // Кворум = 2, connected = 1, поэтому должен вернуть false
        $this->assertFalse($checker->isConnected());
    }
}