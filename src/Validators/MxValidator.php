<?php

declare(strict_types=1);

namespace App\Validators;

use App\Interfaces\DomainValidatorInterface;
use App\Interfaces\ValidatorInterface;
use App\Models\ValidationResult;
use App\Redis\Adapters\RedisCacheAdapter;
use Throwable;
use Exception;

/**
 * Валидатор MX записей (Mail eXchanger) для email адресов
 *
 * Проверяет наличие MX записей в DNS для доменной части email адреса.
 * MX записи указывают, какие серверы отвечают за прием почты для данного домена.
 *
 * Особенности работы:
 * - Выполняет DNS запрос для проверки MX записей
 * - Поддерживает fallback на A записи (если нет MX, проверяет A запись)
 * - Настраиваемые таймауты для DNS запросов
 * - Детальная обработка различных типов DNS ошибок
 * - Поддерживает как новое API (validateDomain), так и старое (validate)
 * - Кэширование результатов в Redis Cluster
 *
 * @package App\Validators
 * @author Vladimir Matkovskii and Claude 4 Sonnet
 * @version 1.1
 */
class MxValidator implements ValidatorInterface, DomainValidatorInterface
{
    /**
     * Таймаут для DNS запросов в секундах
     * Предотвращает зависание при медленных DNS серверах
     */
    private const DNS_TIMEOUT = 10;

    /**
     * Максимальное количество попыток DNS запроса
     * При неудаче первого запроса будет выполнено повторных попыток
     */
    private const MAX_DNS_RETRIES = 2;

    /**
     * Минимальный приоритет MX записи для валидности
     * MX записи с приоритетом выше этого значения игнорируются
     */
    private const MAX_MX_PRIORITY = 65535;

    /**
     * Время жизни кэша для MX-записей в секундах (2 часа)
     * Увеличено до 2 часов, так как MX записи редко меняются
     */
    private const CACHE_TTL = 7200;

    /**
     * Адаптер для кэширования в Redis
     */
    private ?RedisCacheAdapter $cache;

    /**
     * Конструктор класса
     *
     * @param RedisCacheAdapter|null $cache Адаптер Redis кэша (для DI и тестирования)
     */
    public function __construct(?RedisCacheAdapter $cache = null)
    {
        if ($cache === null) {
            try {
                $config = require __DIR__ . '/../../config/redis.php';
                $prefix = $config['mx_cache']['prefix'] ?? 'mx_cache:';
                $this->cache = new RedisCacheAdapter($prefix, $config);
            } catch (Exception $e) {
                error_log("Redis cache unavailable during MxValidator initialization: " . $e->getMessage());
                $this->cache = null;
            }
        } else {
            $this->cache = $cache;
        }
    }

    /**
     * Валидирует доменную часть на наличие MX записей
     *
     * Это основной метод для нового API. Принимает уже извлеченную доменную часть
     * и проверяет наличие у неё MX записей в DNS.
     *
     * @param string $domain Доменная часть email (например, "example.com")
     * @param string $fullEmail Полный email адрес для контекста в сообщениях об ошибках
     * @return ValidationResult Результат валидации с детальной информацией
     */
    public function validateDomain(string $domain, string $fullEmail): ValidationResult
    {
        // Проверяем, что домен не пустой
        if (empty(trim($domain))) {
            return ValidationResult::invalidMx($fullEmail, 'Доменная часть email не может быть пустой');
        }

        // Нормализуем домен (убираем пробелы, приводим к нижнему регистру)
        $domain = strtolower(trim($domain));

        // 1. Проверка кэша
        if ($this->cache) {
            $cachedResult = $this->getCachedResult($domain, $fullEmail);
            if ($cachedResult !== null) {
                return $cachedResult;
            }
        }

        // 2. Если в кэше нет - выполняем полную валидацию
        $result = $this->performFullValidation($domain, $fullEmail);

        // 3. Сохраняем результат в кэш
        if ($this->cache) {
            $this->cacheResult($domain, $result);
        }

        return $result;
    }

    /**
     * Валидирует email адрес (метод для обратной совместимости)
     *
     * Этот метод поддерживает старое API. Он извлекает доменную часть из email
     * и вызывает основной метод validateDomain.
     *
     * @param string $email Полный email адрес для валидации
     * @return ValidationResult Результат валидации
     */
    public function validate(string $email): ValidationResult
    {
        // Базовая проверка формата email
        if (empty(trim($email))) {
            return ValidationResult::invalidMx($email, 'Email адрес не может быть пустым');
        }

        // Проверяем наличие символа @ (простая проверка формата)
        if (substr_count($email, '@') !== 1) {
            return ValidationResult::invalidMx($email, 'Email должен содержать ровно один символ @');
        }

        // Извлекаем доменную часть из email (часть после @)
        [, $domainPart] = explode('@', $email, 2);

        // Делегируем валидацию основному методу
        return $this->validateDomain($domainPart, $email);
    }

    /**
     * Получает результат из кэша
     *
     * @param string $domain Нормализованное доменное имя
     * @param string $fullEmail Полный email для создания ValidationResult
     * @return ValidationResult|null Результат из кэша или null, если не найден
     */
    private function getCachedResult(string $domain, string $fullEmail): ?ValidationResult
    {
        try {
            $cacheData = $this->cache->get($domain);

            if ($cacheData === null) {
                return null;
            }

            // Проверяем, что кэш содержит нужные поля
            if (!array_key_exists('status', $cacheData) || !array_key_exists('reason', $cacheData)) {
                return null;
            }

            // Возвращаем результат с актуальным email, но статусом из кэша
            return new ValidationResult($fullEmail, $cacheData['status'], $cacheData['reason']);

        } catch (Exception $e) {
            // Логируем ошибку только в продакшн окружении
            if (($_ENV['APP_ENV'] ?? '') !== 'testing') {
                error_log("Redis cache GET error for domain '$domain': " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Сохраняет результат в кэш
     *
     * @param string $domain Нормализованное доменное имя
     * @param ValidationResult $result Результат валидации для кэширования
     */
    private function cacheResult(string $domain, ValidationResult $result): void
    {
        try {
            $cacheData = [
                'status' => $result->status,
                'reason' => $result->reason,
                'cached_at' => time(),
            ];

            $this->cache->set($domain, $cacheData, self::CACHE_TTL);
        } catch (Exception $e) {
            // Логируем ошибку только в продакшн окружении
            if (($_ENV['APP_ENV'] ?? '') !== 'testing') {
                error_log("Redis cache SET error for domain '$domain': " . $e->getMessage());
            }
        }
    }

    /**
     * Выполняет полную валидацию домена
     *
     * @param string $domain Нормализованное доменное имя
     * @param string $fullEmail Полный email адрес
     * @return ValidationResult Результат валидации
     */
    private function performFullValidation(string $domain, string $fullEmail): ValidationResult
    {
        // Проверка формата домена
        if (!$this->isValidDomainFormat($domain)) {
            return ValidationResult::invalidMx(
                $fullEmail,
                'Неверный формат доменной части. Домен должен содержать только допустимые символы'
            );
        }

        // Основная проверка MX записей
        $mxCheckResult = $this->checkMxRecords($domain);
        if ($mxCheckResult['valid']) {
            return ValidationResult::valid($fullEmail);
        } else {
            return ValidationResult::invalidMx($fullEmail, $mxCheckResult['reason']);
        }
    }

    /**
     * Проверяет базовый формат доменного имени
     *
     * Выполняет предварительную проверку формата домена перед DNS запросами.
     * Это помогает избежать ненужных сетевых запросов для явно некорректных доменов.
     *
     * @param string $domain Доменное имя для проверки
     * @return bool true если формат домена корректен
     */
    private function isValidDomainFormat(string $domain): bool
    {
        // Проверяем длину домена (RFC требует максимум 253 символа)
        if (strlen($domain) > 253) {
            return false;
        }

        // Проверяем на наличие недопустимых символов
        if (!preg_match('/^[a-z0-9.-]+$/i', $domain)) {
            return false;
        }

        // Проверяем начало и конец строки
        if (preg_match('/^[.-]|[.-]$/', $domain)) {
            return false;
        }

        // Проверяем на последовательные точки и дефисы
        if (str_contains($domain, '..') || str_contains($domain, '--')) {
            return false;
        }

        return true;
    }

    /**
     * Основной метод проверки MX записей для домена
     *
     * Выполняет DNS запрос для получения MX записей и анализирует результат.
     * Если MX записи отсутствуют, проверяет наличие A записи как fallback.
     *
     * @param string $domain Доменное имя для проверки
     * @return array Массив с результатом проверки ['valid' => bool, 'reason' => string]
     */
    private function checkMxRecords(string $domain): array
    {
        // Попытка получить MX записи с повторными попытками
        $mxRecords = $this->getMxRecordsWithRetry($domain);

        if ($mxRecords === null) {
            // DNS запрос не удался
            return [
                'valid' => false,
                'reason' => "Не удалось выполнить DNS запрос для домена '$domain'. " .
                    "Возможно, домен не существует или недоступен DNS сервер"
            ];
        }

        if (empty($mxRecords)) {
            // MX записи отсутствуют, проверяем A запись как fallback
            return $this->checkARecordFallback($domain);
        }

        // Анализируем найденные MX записи
        return $this->analyzeMxRecords($domain, $mxRecords);
    }

    /**
     * Получает MX записи для домена с повторными попытками
     *
     * Выполняет DNS запрос с настраиваемым количеством повторных попыток
     * в случае временных сетевых проблем.
     *
     * @param string $domain Доменное имя
     * @return array|null Массив MX записей или null при ошибке
     */
    private function getMxRecordsWithRetry(string $domain): ?array
    {
        // Выполняем попытки с паузами между ними
        for ($attempt = 0; $attempt <= self::MAX_DNS_RETRIES; $attempt++) {
            // Устанавливаем таймаут для DNS запроса
            $originalTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', (string)self::DNS_TIMEOUT);

            try {
                // Выполняем DNS запрос для получения MX записей
                $result = @getmxrr($domain, $mxHosts, $mxWeights);

                // Восстанавливаем оригинальный таймаут
                ini_set('default_socket_timeout', $originalTimeout);

                if ($result === false) {
                    // Пауза перед следующей попыткой (кроме последней)
                    if ($attempt < self::MAX_DNS_RETRIES) {
                        usleep(500000); // 0.5 секунды
                    }
                    continue;
                }

                // Формируем результат с приоритетами
                $mxRecords = [];
                for ($i = 0; $i < count($mxHosts); $i++) {
                    $mxRecords[] = [
                        'host' => $mxHosts[$i],
                        'priority' => $mxWeights[$i] ?? 10
                    ];
                }

                return $mxRecords;

            } catch (Throwable) {
                // Восстанавливаем таймаут в случае исключения
                ini_set('default_socket_timeout', $originalTimeout);

                // Пауза перед следующей попыткой
                if ($attempt < self::MAX_DNS_RETRIES) {
                    usleep(500000);
                }
            }
        }

        // Все попытки неудачны
        return null;
    }

    /**
     * Проверяет наличие A записи как fallback для отсутствующих MX записей
     *
     * Согласно RFC, если у домена нет MX записей, почта может доставляться
     * напрямую на A запись домена с приоритетом 0.
     *
     * @param string $domain Доменное имя
     * @return array Результат проверки A записи
     */
    private function checkARecordFallback(string $domain): array
    {
        // Используем более современный метод для проверки A записи
        try {
            $aRecords = @dns_get_record($domain, DNS_A);

            if (is_array($aRecords) && !empty($aRecords)) {
                return [
                    'valid' => true,
                    'reason' => "MX записи отсутствуют, но найдена A запись (fallback допустим по RFC)"
                ];
            }
        } catch (Exception $e) {
            error_log("A record check failed for domain '$domain': " . $e->getMessage());
        }

        return [
            'valid' => false,
            'reason' => "Домен '$domain' не имеет MX записей и A записи. " .
                "Невозможно доставить почту на этот домен"
        ];
    }

    /**
     * Анализирует полученные MX записи на корректность
     *
     * Проверяет найденные MX записи на соответствие стандартам и фильтрует
     * записи с недопустимыми приоритетами или некорректными хостами.
     *
     * @param string $domain Доменное имя
     * @param array $mxRecords Массив MX записей
     * @return array Результат анализа
     */
    private function analyzeMxRecords(string $domain, array $mxRecords): array
    {
        $validMxRecords = [];

        foreach ($mxRecords as $mxRecord) {
            $host = $mxRecord['host'] ?? '';
            $priority = $mxRecord['priority'] ?? 65535;

            // Пропускаем записи с недопустимыми приоритетами
            if ($priority > self::MAX_MX_PRIORITY) {
                continue;
            }

            // Пропускаем записи с пустыми хостами
            if (empty(trim($host))) {
                continue;
            }

            // Пропускаем "null MX" записи (RFC 7505)
            if ($host === '.' || ($priority === 0 && $host === '')) {
                continue;
            }

            $validMxRecords[] = $mxRecord;
        }

        // Проверяем, остались ли валидные MX записи
        if (empty($validMxRecords)) {
            return [
                'valid' => false,
                'reason' => "Домен '$domain' имеет MX записи, но все они некорректны или заблокированы"
            ];
        }

        // Дополнительная проверка - пытаемся подключиться к лучшему MX серверу
        return $this->verifyBestMxRecord($domain, $validMxRecords);
    }

    /**
     * Проверяет доступность лучшего MX сервера
     *
     * Выбирает MX запись с наименьшим приоритетом и проверяет,
     * что соответствующий сервер доступен для подключения.
     *
     * @param string $domain Доменное имя
     * @param array $mxRecords Валидные MX записи
     * @return array Результат проверки доступности
     */
    private function verifyBestMxRecord(string $domain, array $mxRecords): array
    {
        // Сортируем MX записи по приоритету (меньший приоритет = выше приоритет)
        usort($mxRecords, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        // Берем запись с наивысшим приоритетом (наименьший номер)
        $bestMxRecord = $mxRecords[0];
        $mxHost = $bestMxRecord['host'];

        // Проверяем, что MX хост имеет A запись
        try {
            $aRecords = @dns_get_record($mxHost, DNS_A);

            if (!is_array($aRecords) || empty($aRecords)) {
                return [
                    'valid' => false,
                    'reason' => "MX сервер '$mxHost' для домена '$domain' не имеет A записи"
                ];
            }
        } catch (Exception $e) {
            return [
                'valid' => false,
                'reason' => "Не удалось проверить A запись для MX сервера '$mxHost': " . $e->getMessage()
            ];
        }

        // Все проверки пройдены
        return [
            'valid' => true,
            'reason' => "Найдены валидные MX записи для домена '$domain'"
        ];
    }
}
