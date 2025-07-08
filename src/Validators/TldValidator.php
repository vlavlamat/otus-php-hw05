<?php

declare(strict_types=1);

namespace App\Validators;

use App\Interfaces\ValidatorInterface;
use App\Interfaces\DomainValidatorInterface;
use App\ValidationResult;
use App\Cache\RedisCacheAdapter;
use Exception;

/**
 * Валидатор TLD (Top Level Domain) email адресов с Redis кэшированием
 *
 * Проверяет доменную часть email адреса на соответствие официальному списку
 * доменов верхнего уровня, поддерживаемых IANA (Internet Assigned Numbers Authority).
 *
 * Особенности работы:
 * - Загружает актуальный список TLD с официального сайта IANA
 * - Кэширует данные в Redis Cluster для высокой производительности
 * - Имеет резервный список TLD на случай недоступности IANA и Redis
 * - Поддерживает как новое API (validateDomain), так и старое (validate)
 * - Использует распределенный кэш между всеми инстансами приложения
 *
 * @package App\Validators
 * @author Vladimir Matkovskii and Claude 4 Sonnet
 * @version 3.0
 */
class TldValidator implements ValidatorInterface, DomainValidatorInterface
{
    /**
     * URL для загрузки официального списка TLD от IANA
     */
    private const IANA_TLD_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';

    /**
     * Ключ для хранения списка TLD в Redis кэше
     */
    private const REDIS_TLD_CACHE_KEY = 'tlds_list';

    /**
     * Ключ для хранения метаданных о кэше (время загрузки, версия и т.д.)
     */
    private const REDIS_TLD_METADATA_KEY = 'tlds_metadata';

    /**
     * Время жизни кэша в секундах (24 часа)
     */
    private const CACHE_DURATION = 24 * 60 * 60;

    /**
     * Таймаут для HTTP запросов к IANA в секундах
     */
    private const HTTP_TIMEOUT = 10;

    /**
     * User-Agent для HTTP запросов
     */
    private const USER_AGENT = 'EmailValidator/3.0 (TLD Checker with Redis)';

    /**
     * Массив валидных TLD в верхнем регистре
     *
     * @var array<string> Список валидных TLD
     */
    private array $validTlds = [];

    /**
     * Адаптер Redis кэша
     */
    private readonly ?RedisCacheAdapter $cache;

    /**
     * Конструктор класса
     *
     * Создание Redis адаптера согласно алгоритму:
     * - Подключение к Redis Cluster
     * - Настройка префикса ключей: tld_cache:
     *
     * @param RedisCacheAdapter|null $cache Адаптер Redis кэша (для DI и тестирования)
     */
    public function __construct(?RedisCacheAdapter $cache = null)
    {
        // Автоматически создаем Redis адаптер если не передан (согласно алгоритму)
        if ($cache === null) {
            try {
                $this->cache = new RedisCacheAdapter();
            } catch (Exception $e) {
                // Если Redis недоступен, продолжаем без кэша
                error_log("Redis cache unavailable during TldValidator initialization: " . $e->getMessage());
                $this->cache = null;
            }
        } else {
            $this->cache = $cache;
        }

        // Загружаем список TLD при создании объекта
        $this->loadValidTlds();
    }

    /**
     * Валидирует доменную часть email на соответствие официальному списку TLD
     *
     * Это основной метод для нового API. Принимает уже извлеченную доменную часть
     * и проверяет её соответствие официальному списку доменов верхнего уровня IANA.
     *
     * @param string $domain Доменная часть email (например, "example.com")
     * @param string $fullEmail Полный email адрес для контекста в сообщениях об ошибках
     * @return ValidationResult Результат валидации с детальной информацией
     */
    public function validateDomain(string $domain, string $fullEmail): ValidationResult
    {
        if (empty(trim($domain))) {
            return ValidationResult::invalidTld($fullEmail, 'Доменная часть email не может быть пустой');
        }

        $tld = $this->extractTld($domain);

        if (empty($tld)) {
            return ValidationResult::invalidTld(
                $fullEmail,
                'Невозможно определить TLD домена. Домен должен содержать хотя бы одну точку'
            );
        }

        if (!$this->isTldValid($tld)) {
            return ValidationResult::invalidTld(
                $fullEmail,
                "TLD '$tld' не найден в списке официальных доменов IANA. " .
                "Возможно, это опечатка или несуществующий домен верхнего уровня"
            );
        }

        return ValidationResult::valid($fullEmail);
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
        $trimmedEmail = trim($email);
        if (empty($trimmedEmail)) {
            return ValidationResult::invalidTld($email, 'Email адрес не может быть пустым');
        }

        $atCount = substr_count($email, '@');
        if ($atCount !== 1) {
            return ValidationResult::invalidTld($email, 'Email должен содержать ровно один символ @');
        }

        [, $domainPart] = explode('@', $email, 2);
        return $this->validateDomain($domainPart, $email);
    }

    /**
     * Извлекает TLD из доменной части
     *
     * @param string $domain Доменная часть email
     * @return string TLD в верхнем регистре
     */
    private function extractTld(string $domain): string
    {
        $parts = explode('.', trim($domain));
        return strtoupper(array_slice($parts, -1)[0] ?? '');
    }

    /**
     * Проверяет валидность TLD согласно списку IANA
     *
     * @param string $tld TLD для проверки
     * @return bool true если TLD валиден
     */
    private function isTldValid(string $tld): bool
    {
        return in_array(strtoupper($tld), $this->validTlds, true);
    }

    /**
     * Основной метод загрузки списка валидных TLD с поддержкой Redis кэша
     *
     * Пытается загрузить список TLD в следующем порядке:
     * 1. Из Redis кэша (если доступен)
     * 2. С официального сайта IANA
     * 3. Использует встроенный резервный список
     */
    private function loadValidTlds(): void
    {
        // Этап 1: Попытка загрузки из Redis кэша
        if ($this->loadFromRedisCache()) {
            return;
        }

        // Этап 2: Попытка загрузки с IANA
        if ($this->loadFromIana()) {
            // Сохраняем в Redis кэш для будущих использований
            $this->saveToRedisCache();
            return;
        }

        // Этап 3: Fallback - используем встроенный резервный список
        $this->loadFallbackTlds();
    }

    /**
     * Загружает список TLD из Redis кэша
     *
     * Проверяет наличие кэшированного списка TLD в Redis и загружает его.
     * Также загружает метаданные о кэше для логирования.
     *
     * @return bool true если кэш успешно загружен
     */
    private function loadFromRedisCache(): bool
    {
        if (!$this->cache?->exists(self::REDIS_TLD_CACHE_KEY)) {
            return false; // Redis недоступен или кэш не существует
        }

        try {
            // Загружаем данные из кэша
            $cachedTlds = $this->cache->get(self::REDIS_TLD_CACHE_KEY);
            $metadata = $this->cache->get(self::REDIS_TLD_METADATA_KEY);

            // Проверяем валидность загруженных данных
            if (!is_array($cachedTlds) || empty($cachedTlds)) {
                return false;
            }

            // Проверяем метаданные с использованием современного синтаксиса
            if (is_array($metadata)) {
                $loadedAt = $metadata['loaded_at'] ?? 0;
                $version = $metadata['version'] ?? 'unknown';
                $tldCount = count($cachedTlds);
                $loadedTime = date('Y-m-d H:i:s', $loadedAt);

                error_log("TLD cache loaded from Redis: $version, $tldCount TLDs, loaded at $loadedTime");
            }

            $this->validTlds = $cachedTlds;
            return true;

        } catch (Exception $e) {
            error_log("Error loading TLD cache from Redis: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Загружает актуальный список TLD с официального сайта IANA
     *
     * Выполняет HTTP запрос к официальному API IANA для получения
     * актуального списка доменов верхнего уровня.
     *
     * @return bool true если список успешно загружен
     */
    private function loadFromIana(): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => self::HTTP_TIMEOUT,
                'user_agent' => self::USER_AGENT,
                'method' => 'GET',
                'header' => [
                    'Accept: text/plain',
                    'Cache-Control: no-cache'
                ]
            ]
        ]);

        $content = @file_get_contents(self::IANA_TLD_URL, false, $context);

        if ($content === false) {
            return false;
        }

        $lines = explode("\n", trim($content));

        // Фильтруем и обрабатываем строки списка TLD
        $tlds = array_map(
            fn(string $line): string => strtoupper($line),
            array_filter(
                array_map(fn(string $line): string => trim($line), $lines),
                fn(string $line): bool => !empty($line) && !str_starts_with($line, '#')
            )
        );

        // Проверяем минимальное количество TLD (должно быть разумное количество)
        $tldCount = count($tlds);
        if ($tldCount < 100) {
            error_log("IANA TLD list seems incomplete: only $tldCount TLDs found");
            return false;
        }

        $this->validTlds = array_values($tlds); // Переиндексируем массив
        error_log("TLD list loaded from IANA: $tldCount TLDs");
        return true;
    }

    /**
     * Сохраняет текущий список TLD в Redis кэш
     *
     * Сохраняет загруженный список TLD и метаданные о кэше в Redis
     * для использования в последующих запросах.
     */
    private function saveToRedisCache(): void
    {
        if (!$this->cache || empty($this->validTlds)) {
            return;
        }

        try {
            // Сохраняем список TLD
            $success = $this->cache->set(
                self::REDIS_TLD_CACHE_KEY,
                $this->validTlds,
                self::CACHE_DURATION
            );

            if ($success) {
                $tldCount = count($this->validTlds);

                // Сохраняем метаданные о кэше
                $metadata = [
                    'loaded_at' => time(),
                    'version' => '3.0',
                    'source' => 'IANA',
                    'count' => $tldCount,
                    'url' => self::IANA_TLD_URL
                ];

                $this->cache->set(
                    self::REDIS_TLD_METADATA_KEY,
                    $metadata,
                    self::CACHE_DURATION
                );

                error_log("TLD cache saved to Redis: $tldCount TLDs");
            }

        } catch (Exception $e) {
            error_log("Error saving TLD cache to Redis: " . $e->getMessage());
        }
    }

    /**
     * Загружает резервный список TLD
     *
     * Используется как fallback когда Redis и IANA недоступны.
     * Содержит основные общие и страновые домены верхнего уровня.
     */
    private function loadFallbackTlds(): void
    {
        error_log("Using fallback TLD list - Redis and IANA unavailable");

        $this->validTlds = [
            // Общие TLD (gTLD)
            'COM', 'NET', 'ORG', 'EDU', 'GOV', 'MIL', 'INT', 'INFO', 'BIZ', 'NAME',

            // Новые общие TLD
            'TECH', 'ONLINE', 'SITE', 'WEBSITE', 'STORE', 'APP', 'BLOG', 'NEWS',

            // Страновые коды (ccTLD) - основные
            'AC', 'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT',
            'AU', 'AW', 'AX', 'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ',
            'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW', 'BY', 'BZ', 'CA',
            'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU',
            'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE',
            'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB',
            'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS',
            'GT', 'GU', 'GW', 'GY', 'HK', 'HM', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL',
            'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG',
            'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC', 'LI',
            'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG',
            'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV',
            'MW', 'MX', 'MY', 'MZ', 'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP',
            'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN',
            'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB',
            'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR',
            'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK',
            'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US',
            'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'YE', 'YT',
            'ZA', 'ZM', 'ZW'
        ];
    }

    /**
     * Принудительное обновление кэша TLD (для административных целей)
     *
     * @return bool true если обновление прошло успешно
     */
    public function forceRefreshCache(): bool
    {
        if ($this->loadFromIana()) {
            $this->saveToRedisCache();
            return true;
        }
        return false;
    }

    /**
     * Очистка кэша TLD
     *
     * Удаляет все данные TLD кэша из Redis, включая список TLD и метаданные.
     * Используется для принудительной очистки кэша в административных целях.
     *
     * @return bool true если кэш успешно очищен
     */
    public function clearCache(): bool
    {
        if (!$this->cache) {
            return false;
        }

        try {
            // Удаляем ключ со списком TLD
            $tldDeleted = $this->cache->delete(self::REDIS_TLD_CACHE_KEY);

            // Удаляем ключ с метаданными
            $metadataDeleted = $this->cache->delete(self::REDIS_TLD_METADATA_KEY);

            // Логируем операцию
            if ($tldDeleted || $metadataDeleted) {
                error_log("TLD cache cleared from Redis");
                return true;
            }

            return false;

        } catch (Exception $e) {
            error_log("Error clearing TLD cache from Redis: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получение информации о состоянии кэша
     *
     * Возвращает детальную информацию о состоянии Redis кэша,
     * включая TTL, метаданные и количество загруженных TLD.
     *
     * @return array Информация о кэше
     */
    public function getCacheInfo(): array
    {
        if (!$this->cache) {
            return [
                'status' => 'redis_unavailable',
                'ttl_seconds' => -2,
                'ttl_human' => 'redis_unavailable',
                'metadata' => null,
                'current_tlds_count' => count($this->validTlds)
            ];
        }

        $metadata = $this->cache->get(self::REDIS_TLD_METADATA_KEY);
        $ttl = $this->cache->getTtl(self::REDIS_TLD_CACHE_KEY);
        $cacheExists = $this->cache->exists(self::REDIS_TLD_CACHE_KEY);

        $status = match ($cacheExists) {
            true => 'cached',
            false => 'not_cached'
        };

        $ttlHuman = match (true) {
            $ttl > 0 => gmdate('H:i:s', $ttl),
            default => 'expired'
        };

        return [
            'status' => $status,
            'ttl_seconds' => $ttl,
            'ttl_human' => $ttlHuman,
            'metadata' => $metadata ?: null,
            'current_tlds_count' => count($this->validTlds)
        ];
    }
}
