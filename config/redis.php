<?php

declare(strict_types=1);

/**
 * Конфигурация Redis Cluster
 *
 * Этот файл содержит настройки для подключения к Redis Cluster,
 * включая список узлов, настройки кворума и таймауты.
 * Поддерживает переменные окружения для гибкой настройки.
 */

return [
    'cluster' => [
        // Список узлов Redis Cluster (5 мастеров + 5 слейвов)
        'nodes' => explode(',', $_ENV['REDIS_CLUSTER_NODES'] ??
            'redis-node1:6379,redis-node2:6379,redis-node3:6379,redis-node4:6379,redis-node5:6379,redis-node6:6379,redis-node7:6379,redis-node8:6379,redis-node9:6379,redis-node10:6379'
        ),

        // Минимальное количество мастер-узлов для работоспособности кластера
        // 3 из 5 мастеров (больше половины)
        'quorum' => (int)($_ENV['REDIS_QUORUM'] ?? 3),

        // Таймаут подключения к Redis в секундах
        'timeout' => (int)($_ENV['REDIS_TIMEOUT'] ?? 5),

        // Таймаут чтения данных в секундах
        'read_timeout' => (int)($_ENV['REDIS_READ_TIMEOUT'] ?? 5),

        // Настройки для сессий
        'session' => [
            // Префикс для ключей сессий
            'prefix' => $_ENV['REDIS_SESSION_PREFIX'] ?? 'otus_hw04:',

            // Время жизни сессии в секундах (по умолчанию 24 ЧАСА)
            'gc_maxlifetime' => (int)($_ENV['REDIS_SESSION_LIFETIME'] ?? 86400),

            // Вероятность запуска сборщика мусора
            'gc_probability' => (int)($_ENV['REDIS_GC_PROBABILITY'] ?? 1),
            'gc_divisor' => (int)($_ENV['REDIS_GC_DIVISOR'] ?? 100)
        ]
    ],
    // Настройки для мониторинга
    'monitoring' => [
        // Интервал проверки состояния узлов в секундах
        'check_interval' => (int)($_ENV['REDIS_CHECK_INTERVAL'] ?? 30),

        // Таймаут для ping-запросов в секундах
        'ping_timeout' => (int)($_ENV['REDIS_PING_TIMEOUT'] ?? 2)
    ]
];
