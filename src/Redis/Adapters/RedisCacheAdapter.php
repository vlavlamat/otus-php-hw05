<?php

declare(strict_types=1);

namespace App\Redis\Adapters;

use Exception;
use RedisCluster;
use RedisClusterException;

/**
 * Адаптер для работы с Redis Cluster кэшем
 *
 * Предоставляет упрощенный интерфейс для кэширования данных в Redis Cluster
 * с поддержкой TTL, сериализации и обработки ошибок подключения.
 */
class RedisCacheAdapter
{
    private RedisCluster $cluster;
    private string $keyPrefix;

    /**
     * @param string $keyPrefix Префикс для ключей кэша (например, 'tld_cache:')
     * @param array|null $config Конфигурация Redis (если null - загружается из config/redis.php)
     * @throws RedisClusterException при ошибке подключения
     */
    public function __construct(string $keyPrefix, ?array $config = null)
    {
        $config = $config ?? require __DIR__ . '/../../../config/redis.php';

        $this->cluster = new RedisCluster(
            null,
            $config['cluster']['nodes'],
            $config['cluster']['timeout'] ?: 5,
            $config['cluster']['read_timeout'] ?: 5
        );

        // Устанавливаем префикс для ключей кэша
        $this->keyPrefix = $keyPrefix;
    }

    /**
     * Сохраняет данные в кэш с указанным TTL
     *
     * @param string $key Ключ кэша
     * @param mixed $data Данные для сохранения
     * @param int $ttl Время жизни в секундах
     * @return bool true при успешном сохранении
     */
    public function set(string $key, mixed $data, int $ttl): bool
    {
        try {
            $serializedData = serialize($data);
            $fullKey = $this->keyPrefix . $key;

            return $this->cluster->setex($fullKey, $ttl, $serializedData);
        } catch (Exception $e) {
            // Логируем ошибку и возвращаем false (кроме тестового окружения)
            if ((getenv('APP_ENV') ?? '') !== 'testing') {
                error_log("Redis cache SET error for key '$key': " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Получает данные из кэша
     *
     * @param string $key Ключ кэша
     * @return mixed|null Данные из кэша или null при отсутствии/ошибке
     */
    public function get(string $key): mixed
    {
        try {
            $fullKey = $this->keyPrefix . $key;
            $serializedData = $this->cluster->get($fullKey);

            if ($serializedData === false) {
                return null; // Ключ не найден
            }

            return unserialize($serializedData);
        } catch (Exception $e) {
            // Логируем ошибку только в продакшн окружении
            if ((getenv('APP_ENV') ?? '') !== 'testing') {
                error_log("Redis cache GET error for key '$key': " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Проверяет существование ключа в кэше
     *
     * @param string $key Ключ для проверки
     * @return bool true если ключ существует
     */
    public function exists(string $key): bool
    {
        try {
            $fullKey = $this->keyPrefix . $key;
            return $this->cluster->exists($fullKey) > 0;
        } catch (Exception $e) {
            // Логируем ошибку только в продакшн окружении
            if ((getenv('APP_ENV') ?? '') !== 'testing') {
                error_log("Redis cache EXISTS error for key '$key': " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Получает TTL (время жизни) ключа
     *
     * @param string $key Ключ для проверки
     * @return int TTL в секундах (-1 если нет TTL, -2 если ключ не существует)
     */
    public function getTtl(string $key): int
    {
        try {
            $fullKey = $this->keyPrefix . $key;
            return $this->cluster->ttl($fullKey);
        } catch (Exception $e) {
            // Логируем ошибку только в продакшн окружении
            if ((getenv('APP_ENV') ?? '') !== 'testing') {
                error_log("Redis cache TTL error for key '$key': " . $e->getMessage());
            }
            return -2; // Ключ не существует
        }
    }

    /**
     * Удаляет ключ из кэша
     *
     * Этот метод используется в forceRefreshCache()
     *
     * @param string $key Ключ для удаления
     * @return bool true при успешном удалении
     */
    public function delete(string $key): bool
    {
        try {
            $fullKey = $this->keyPrefix . $key;
            return $this->cluster->del($fullKey) > 0;
        } catch (Exception $e) {
            // Логируем ошибку только в продакшн окружении
            if ((getenv('APP_ENV') ?? '') !== 'testing') {
                error_log("Redis cache DELETE error for key '$key': " . $e->getMessage());
            }
            return false;
        }
    }
}
