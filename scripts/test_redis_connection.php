<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Redis\Adapters\RedisCacheAdapter;

echo "=== Тестирование подключения к Redis Cluster ===\n";
echo "Время: " . date('Y-m-d H:i:s') . "\n\n";

// Загружаем конфиг Redis
$configFile = __DIR__ . '/../config/redis.php';
if (!file_exists($configFile)) {
    echo "✗ Не найден файл конфигурации redis.php\n";
    exit(1);
}
$config = require $configFile;

// Проверяем параметры подключения
echo "--- Конфигурация подключения ---\n";
print_r($config['cluster']);

try {
    // Создаём адаптер (используем PREFIX как в env-файле или явный из конфига)
    $prefix = $config['tld_cache_prefix'] ?? 'tld_cache:';
    $cache = new RedisCacheAdapter($prefix, $config);

    echo "✓ Redis адаптер создан успешно\n\n";

    // Тестовое ключ/значение
    $testKey = $prefix . 'test_connection_' . time();
    $testData = ['test' => 'data', 'timestamp' => time()];

    echo "--- Тест: Запись в Redis ---\n";
    if ($cache->set($testKey, $testData, 60)) {
        echo "✓ Успешная запись по ключу $testKey\n";
    } else {
        echo "✗ Ошибка записи в Redis\n";
        exit(1);
    }

    echo "--- Тест: Чтение из Redis ---\n";
    $retrieved = $cache->get($testKey);
    if ($retrieved === false) {
        echo "✗ Не удалось получить значение по ключу\n";
        exit(1);
    }
    echo "✓ Чтение успешно. Данные:\n";
    print_r($retrieved);

    // Проверка наличия ключа
    echo "--- Тест: exists() ---\n";
    if ($cache->exists($testKey)) {
        echo "✓ exists() работает корректно\n";
    } else {
        echo "✗ exists() вернул false — ключ не найден\n";
    }

    // Удаление после теста
    echo "--- Тест: del() ---\n";
    $cache->delete($testKey);
    echo "✓ Ключ удалён\n";

    // Диагностика доступности всех нод
    echo "--- Диагностика всех нод кластера ---\n";
    foreach ($config['cluster']['nodes'] as $node) {
        list($host, $port) = explode(':', $node, 2);
        $fp = @fsockopen($host, $port, $errno, $errstr, 2);
        if ($fp) {
            echo "✓ $host:$port — доступен\n";
            fclose($fp);
        } else {
            echo "✗ $host:$port — ошибка соединения [$errno]: $errstr\n";
        }
    }

    echo "\n=== Проверка завершена ===\n";

} catch (RedisClusterException $e) {
    echo "✗ Ошибка RedisCluster: {$e->getMessage()}\n";
    exit(2);
} catch (Throwable $e) {
    echo "✗ Неожиданная ошибка: " . $e->getMessage() . "\n";
    exit(99);
}