<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Redis\Adapters\RedisCacheAdapter;
use App\Validators\TldValidator;

/**
 * Тестовый скрипт для проверки работы TLD кэша в Redis Cluster
 *
 * ВАЖНО: Этот файл должен запускаться внутри контейнера PHP, а не из хост-системы!
 *
 * Запуск внутри контейнера:
 * $ docker exec -it php-fpm1-hw05 php scripts/test_tld_cache.php
 *
 * Скрипт выполняет:
 * 1. Инициализацию TldValidator и подключение к Redis
 * 2. Тестирование валидации email адресов
 * 3. Проверку операций с кэшем (обновление и информация)
 * 4. Тестирование производительности
 * 5. Проверку отказоустойчивости
 */

echo "🔍 Тестирование TLD кэша в Redis Cluster\n";
echo "==========================================\n\n";

try {
    echo "📋 ЭТАП 1: Инициализация TldValidator\n";
    echo "------------------------------------\n";

    $startTime = microtime(true);
    $validator = new TldValidator();
    $initTime = (microtime(true) - $startTime) * 1000;

    echo "✅ TldValidator создан за {$initTime}ms\n";

    $cacheInfo = $validator->getCacheInfo();
    echo "📊 Информация о кэше:\n";
    echo "   - Статус: {$cacheInfo['status']}\n";
    echo "   - TTL: {$cacheInfo['ttl_human']} ({$cacheInfo['ttl_seconds']} сек)\n";
    echo "   - Количество TLD: {$cacheInfo['current_tlds_count']}\n";

    if ($cacheInfo['metadata']) {
        $metadata = $cacheInfo['metadata'];
        echo "   - Источник: {$metadata['source']}\n";
        echo "   - Версия: {$metadata['version']}\n";
        echo "   - Загружено: " . date('Y-m-d H:i:s', $metadata['loaded_at']) . "\n";
    }

    echo "\n";

    echo "📋 ЭТАП 2: Тестирование валидации email адресов\n";
    echo "-----------------------------------------------\n";

    $testEmails = [
        'user@example.com',
        'admin@google.com',
        'test@microsoft.net',
        'info@company.org',
        'contact@startup.tech',
        'support@site.online',
        'user@invalid.xyz',
        'test@domain.fake',
        'admin@company.ru',
        'info@startup.de',
    ];

    foreach ($testEmails as $email) {
        $startTime = microtime(true);
        $result = $validator->validate($email);
        $validationTime = (microtime(true) - $startTime) * 1000;

        $status = $result->isValid() ? '✅' : '❌';
        echo "$status $email - {$validationTime}ms\n";

        if (!$result->isValid()) {
            echo "   Ошибка: $result->reason\n";
        }
    }

    echo "\n";

    echo "📋 ЭТАП 3: Тестирование операций с кэшем\n";
    echo "----------------------------------------\n";

    echo "📊 Текущее состояние кэша:\n";
    $cacheInfo = $validator->getCacheInfo();
    print_r($cacheInfo);

    echo "\n🔄 Принудительное обновление кэша...\n";
    $refreshStart = microtime(true);
    $refreshSuccess = $validator->forceRefreshCache();
    $refreshTime = (microtime(true) - $refreshStart) * 1000;

    if ($refreshSuccess) {
        echo "✅ Кэш обновлен за {$refreshTime}ms\n";
    } else {
        echo "❌ Ошибка обновления кэша\n";
    }

    echo "\n📊 Состояние кэша после обновления:\n";
    $newCacheInfo = $validator->getCacheInfo();
    print_r($newCacheInfo);

    echo "\n";

    echo "📋 ЭТАП 4: Тестирование производительности\n";
    echo "------------------------------------------\n";

    $performanceEmails = [
        'user1@example.com',
        'user2@google.com',
        'user3@microsoft.net',
        'user4@company.org',
        'user5@startup.tech'
    ];

    $totalTime = 0;
    $iterations = 100;

    echo "🔄 Выполнение $iterations итераций валидации...\n";

    for ($i = 0; $i < $iterations; $i++) {
        foreach ($performanceEmails as $email) {
            $startTime = microtime(true);
            $validator->validate($email);
            $totalTime += (microtime(true) - $startTime) * 1000;
        }
    }

    $avgTime = $totalTime / ($iterations * count($performanceEmails));
    echo "📈 Среднее время валидации: {$avgTime}ms\n";
    echo "📈 Общее время: " . ($totalTime / 1000) . " сек\n";

    echo "\n";

    echo "📋 ЭТАП 5: Тестирование отказоустойчивости\n";
    echo "-------------------------------------------\n";

    echo "🔄 Тестирование fallback режима (без Redis)...\n";
    $mockCache = new class extends RedisCacheAdapter {
        private string $keyPrefix;

        public function __construct()
        {
            $this->keyPrefix = 'tld_cache:';
            parent::__construct($this->keyPrefix);
        }

        public function exists(string $key): bool
        {
            return false;
        }

        public function get(string $key): mixed
        {
            return null;
        }

        public function set(string $key, mixed $data, int $ttl): bool
        {
            return false;
        }

        public function getTtl(string $key): int
        {
            return -2;
        }
    };

    $fallbackStart = microtime(true);
    $fallbackValidator = new TldValidator($mockCache);
    $fallbackTime = (microtime(true) - $fallbackStart) * 1000;

    echo "✅ Fallback валидатор создан за {$fallbackTime}ms\n";

    // Тестируем валидацию в fallback режиме
    $fallbackResult = $fallbackValidator->validate('user@example.com');
    echo "📧 Валидация в fallback режиме: " . ($fallbackResult->isValid() ? '✅' : '❌') . "\n";

    $fallbackCacheInfo = $fallbackValidator->getCacheInfo();
    echo "📊 Статус fallback кэша: {$fallbackCacheInfo['status']}\n";

    echo "\n";

    // ЭТАП 6: Демонстрация алгоритма работы
    echo "📋 ЭТАП 6: Демонстрация алгоритма работы\n";
    echo "----------------------------------------\n";

    echo "🔄 Алгоритм работы TLD кэша:\n";
    echo "1️⃣ Попытка загрузки из Redis кэша (самый быстрый)\n";
    echo "2️⃣ Fallback на IANA API (актуальные данные)\n";
    echo "3️⃣ Сохранение в Redis кэш (для будущих запросов)\n";
    echo "4️⃣ Использование fallback списка (если все недоступно)\n";

    echo "\n📊 Временные характеристики:\n";
    echo "   - Redis кэш: ~1-5 мс\n";
    echo "   - IANA API: ~500-2000 мс\n";
    echo "   - Fallback: ~0.1 мс\n";

    echo "\n🛡️ Обработка ошибок:\n";
    echo "   - Redis недоступен → загрузка с IANA → fallback\n";
    echo "   - IANA недоступен → fallback список\n";
    echo "   - Данные повреждены → переход к следующему источнику\n";

    echo "\n✅ Тестирование завершено успешно!\n";

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📍 Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "🔍 Trace:\n" . $e->getTraceAsString() . "\n";
}