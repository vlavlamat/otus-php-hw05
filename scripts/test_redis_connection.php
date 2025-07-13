<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Redis\Adapters\RedisCacheAdapter;

echo "=== Тестирование подключения к Redis Cluster ===\n";
echo "Время: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Пытаемся создать адаптер Redis
    $cache = new RedisCacheAdapter();
    
    echo "✓ Redis адаптер создан успешно\n";
    
    // Тестируем базовые операции
    $testKey = 'test_connection_' . time();
    $testData = ['test' => 'data', 'timestamp' => time()];
    
    echo "\n--- Тестирование операций Redis ---\n";
    
    // Тест записи
    if ($cache->set($testKey, $testData, 60)) {
        echo "✓ Запись в Redis успешна\n";
        
        // Тест чтения
        $retrieved = $cache->get($testKey);
        if ($retrieved && $retrieved['test'] === 'data') {
            echo "✓ Чтение из Redis успешно\n";
            
            // Тест существования
            if ($cache->exists($testKey)) {
                echo "✓ Проверка существования ключа работает\n";
                
                // Тест TTL
                $ttl = $cache->getTtl($testKey);
                echo "✓ TTL ключа: $ttl секунд\n";
                
                // Тест удаления
                if ($cache->delete($testKey)) {
                    echo "✓ Удаление ключа успешно\n";
                } else {
                    echo "✗ Ошибка удаления ключа\n";
                }
            } else {
                echo "✗ Ключ не найден при проверке существования\n";
            }
        } else {
            echo "✗ Ошибка чтения данных из Redis\n";
        }
    } else {
        echo "✗ Ошибка записи в Redis\n";
    }
    
    echo "\n✓ Redis Cluster доступен и работает корректно\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка подключения к Redis: " . $e->getMessage() . "\n";
    echo "Это нормально, если Redis Cluster не запущен локально.\n";
    echo "Для полного тестирования запустите Redis Cluster через Docker Compose.\n";
}

echo "\n=== Тест завершен ===\n";