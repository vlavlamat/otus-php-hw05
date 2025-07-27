<?php

declare(strict_types=1);

namespace App\Bootstrap;

use RuntimeException;

/**
 * Простая валидация переменных окружения для учебного проекта
 *
 * Docker Compose уже загрузил все переменные через env_file директиву.
 * Этот класс только проверяет, что все необходимые переменные корректно
 * загрузились и имеют правильные значения.
 */
class EnvironmentLoader
{
    /**
     * Список обязательных переменных окружения
     *
     * @var string[]
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

    /**
     * Допустимые значения для APP_ENV
     *
     * @var string[]
     */
    private const VALID_APP_ENVIRONMENTS = ['development', 'production'];

    /**
     * Валидация переменных окружения
     *
     * Проверяем, что все необходимые переменные загрузились корректно.
     * @throws RuntimeException При проблемах с окружением
     */
    public static function load(): void
    {
        self::validateRequiredVariables();
        self::validateAppEnvironment();
    }

    /**
     * Проверяет наличие всех обязательных переменных
     *
     * @throws RuntimeException При отсутствии переменных
     */
    private static function validateRequiredVariables(): void
    {
        $missing = [];
        $empty = [];

        foreach (self::REQUIRED_ENV_VARIABLES as $variable) {
            $value = getenv($variable);

            if ($value === false) {
                $missing[] = $variable;
            } elseif (empty(trim($value))) {
                $empty[] = $variable . ' (empty value)';
            }
        }

        if (!empty($missing) || !empty($empty)) {
            $errors = [];

            if (!empty($missing)) {
                $errors[] = 'Отсутствуют переменные окружения: ' . implode(', ', $missing);
            }

            if (!empty($empty)) {
                $errors[] = 'Пустые переменные окружения: ' . implode(', ', $empty);
            }
            throw new RuntimeException(
                'Ошибки переменных окружения: ' . implode(', ', $errors) .
                '. Проверьте файлы env/.env.dev или env/.env.prod.'
            );
        }
    }

    /**
     * Валидирует значение APP_ENV
     *
     * @throws RuntimeException При некорректном APP_ENV
     */
    private static function validateAppEnvironment(): void
    {
        $appEnv = getenv('APP_ENV');

        if (!in_array($appEnv, self::VALID_APP_ENVIRONMENTS, true)) {
            throw new RuntimeException(
                "Недопустимое значение APP_ENV: '$appEnv'. " .
                "Допустимые значения: " . implode(', ', self::VALID_APP_ENVIRONMENTS)
            );
        }
    }
}