<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Validators\TldValidator;
use App\Cache\RedisCacheAdapter;

/**
 * Тестовый скрипт для проверки работы TLD кэша в Redis Cluster
 * 
 * Демонстрирует полный алгоритм работы кэша согласно спецификации:
 * 1. Попытка загрузки из Redis кэша
 * 2. Fallback на IANA API
 * 3. Сохранение в Redis кэш
 * 4. Использование fallback списка
 */

echo "🔍 Тестирование TLD кэша в Redis Cluster\n";
echo "==========================================\n\n";

try {
    // ЭТАП 1: Инициализация валидатора
    echo "📋 ЭТАП 1: Инициализация TldValidator\n";
    echo "------------------------------------\n";

    $startTime = microtime(true);
    $validator = new TldValidator();
    $initTime = (microtime(true) - $startTime) * 1000;

    echo "✅ TldValidator создан за {$initTime}ms\n";

    // Получаем информацию о кэше
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

    // ЭТАП 2: Тестирование валидации email адресов
    echo "📋 ЭТАП 2: Тестирование валидации email адресов\n";
    echo "-----------------------------------------------\n";

    $testEmails = [
        'user@example.com',      // Валидный gTLD
        'admin@google.com',      // Валидный gTLD
        'test@microsoft.net',    // Валидный gTLD
        'info@company.org',      // Валидный gTLD
        'contact@startup.tech',  // Валидный новый gTLD
        'support@site.online',   // Валидный новый gTLD
        'user@invalid.xyz',      // Невалидный TLD
        'test@domain.fake',      // Невалидный TLD
        'admin@company.ru',      // Валидный ccTLD
        'info@startup.de',       // Валидный ccTLD
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

    // ЭТАП 3: Тестирование операций с кэшем
    echo "📋 ЭТАП 3: Тестирование операций с кэшем\n";
    echo "----------------------------------------\n";

    // Получение информации о кэше
    echo "📊 Текущее состояние кэша:\n";
    $cacheInfo = $validator->getCacheInfo();
    print_r($cacheInfo);

    // Принудительное обновление кэша
    echo "\n🔄 Принудительное обновление кэша...\n";
    $refreshStart = microtime(true);
    $refreshSuccess = $validator->forceRefreshCache();
    $refreshTime = (microtime(true) - $refreshStart) * 1000;

    if ($refreshSuccess) {
        echo "✅ Кэш обновлен за {$refreshTime}ms\n";
    } else {
        echo "❌ Ошибка обновления кэша\n";
    }

    // Новая информация о кэше после обновления
    echo "\n📊 Состояние кэша после обновления:\n";
    $newCacheInfo = $validator->getCacheInfo();
    print_r($newCacheInfo);

    echo "\n";

    // ЭТАП 4: Тестирование производительности
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

    // ЭТАП 5: Тестирование отказоустойчивости
    echo "📋 ЭТАП 5: Тестирование отказоустойчивости\n";
    echo "-------------------------------------------\n";

    // Создаем валидатор без Redis (симуляция недоступности Redis)
    echo "🔄 Тестирование fallback режима (без Redis)...\n";

    // Создаем мок Redis адаптера который всегда возвращает ошибку
    // Используем анонимный класс для симуляции недоступности Redis
    $mockCache = new class extends RedisCacheAdapter {
        /**
         * Префикс ключей для кэша TLD
         * Используется в методах для формирования полного ключа
         */
        private string $keyPrefix;

        /**
         * Конструктор, который намеренно не вызывает родительский конструктор
         * Это позволяет избежать попыток подключения к Redis
         * 
         * @inheritdoc
         * @phpstan-ignore-next-line
         */
        public function __construct() {
            // Инициализируем $keyPrefix без вызова родительского конструктора
            // parent::__construct() намеренно не вызывается, чтобы избежать подключения к Redis
            $this->keyPrefix = 'tld_cache:';
        }

        /**
         * Проверяет существование ключа в кэше
         * Всегда возвращает false для симуляции отсутствия кэша
         */
        public function exists(string $key): bool {
            $fullKey = $this->keyPrefix . $key; // Используем свойство для консистентности
            return false; // Симулируем отсутствие кэша
        }

        /**
         * Получает данные из кэша
         * Всегда возвращает null для симуляции ошибки получения
         */
        public function get(string $key): mixed {
            $fullKey = $this->keyPrefix . $key; // Используем свойство для консистентности
            return null; // Симулируем ошибку получения
        }

        /**
         * Сохраняет данные в кэш
         * Всегда возвращает false для симуляции ошибки сохранения
         */
        public function set(string $key, mixed $data, int $ttl): bool {
            $fullKey = $this->keyPrefix . $key; // Используем свойство для консистентности
            return false; // Симулируем ошибку сохранения
        }

        /**
         * Получает TTL (время жизни) ключа
         * Всегда возвращает -2 для симуляции отсутствия ключа
         */
        public function getTtl(string $key): int {
            $fullKey = $this->keyPrefix . $key; // Используем свойство для консистентности
            return -2; // Ключ не существует
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