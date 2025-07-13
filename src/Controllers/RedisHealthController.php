<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Redis\Health\RedisHealthChecker;
use Throwable;

/**
 * HTTP контроллер для мониторинга состояния Redis Cluster
 *
 * Этот класс является специализированным контроллером для проверки состояния
 * Redis Cluster и предоставления детальной информации о здоровье инфраструктуры.
 * Он служит критически важным компонентом для мониторинга и отладки системы.
 *
 * Основные возможности:
 * - Проверка доступности Redis Cluster в режиме реального времени
 * - Мониторинг состояния каждого узла кластера отдельно
 * - Проверка соответствия кворуму для обеспечения отказоустойчивости
 * - Предоставление детальной статистики подключений
 * - Формирование структурированных отчетов о состоянии системы
 * - Поддержка CORS для интеграции с frontend приложениями
 * - Обработка ошибок с детальной диагностикой
 *
 * Архитектурные принципы:
 * - Использует dependency injection для RedisHealthChecker
 * - Возвращает JSON ответы в стандартизированном формате
 * - Включает временные метки для корреляции логов
 * - Предоставляет как общий статус, так и детальную информацию
 * - Поддерживает graceful degradation при частичных сбоях
 *
 * @package App\Redis\Controllers
 * @author Vladimir Matkovskii and Claude 4 Sonnet
 * @version 1.0
 */
class RedisHealthController
{
    /**
     * Экземпляр проверки состояния Redis Cluster
     * Инкапсулирует всю логику мониторинга и диагностики кластера
     *
     * @var RedisHealthChecker
     */
    private RedisHealthChecker $redisChecker;

    /**
     * Конструктор контроллера мониторинга Redis
     *
     * Инициализирует контроллер с необходимым сервисом проверки состояния Redis.
     * Использует dependency injection для улучшения тестируемости и гибкости архитектуры.
     *
     * @param RedisHealthChecker $redisChecker Сервис для проверки состояния Redis Cluster
     */
    public function __construct(RedisHealthChecker $redisChecker)
    {
        $this->redisChecker = $redisChecker;
    }

    /**
     * Основной эндпоинт для проверки состояния Redis Cluster
     *
     * Выполняет полную диагностику состояния Redis Cluster и возвращает
     * детальную информацию о здоровье всех компонентов системы.
     * Этот метод является критически важным для мониторинга инфраструктуры.
     *
     * Формат успешного ответа:
     * {
     *   "status": "OK",
     *   "service": "email-validator",
     *   "version": "1.0.0",
     *   "timestamp": "2024-01-15T10:30:00+00:00",
     *   "server": "web-server-01",
     *   "redis_cluster": "connected",
     *   "redis_details": {
     *     "cluster_status": "healthy",
     *     "connected_nodes": 6,
     *     "total_nodes": 7,
     *     "quorum_required": 4,
     *     "quorum_met": true,
     *     "nodes": {
     *       "127.0.0.1:7000": "connected",
     *       "127.0.0.1:7001": "connected",
     *       ...
     *     }
     *   }
     * }
     *
     * @return void Отправляет JSON ответ напрямую через HTTP
     */
    public function getStatus(): void
    {
        try {
            // Настраиваем HTTP заголовки для JSON API с поддержкой CORS
            $this->setJsonHeaders();

            // Получаем базовую информацию о состоянии Redis Cluster
            $redisConnected = $this->redisChecker->isConnected();
            $clusterStatus = $this->redisChecker->getClusterStatus();

            /**
             * Подсчет статистики узлов кластера
             *
             * Анализируем состояние каждого узла Redis Cluster для определения
             * общего здоровья кластера. Это критически важно для обеспечения
             * отказоустойчивости и предотвращения потери данных.
             *
             * Логика подсчета:
             * - Перебираем статус каждого узла из конфигурации
             * - Считаем только узлы со статусом 'connected'
             * - Игнорируем узлы с ошибками или недоступные узлы
             * - Сравниваем с требуемым кворумом для валидации кластера
             */
            $connectedCount = 0;
            $totalNodes = count($clusterStatus);

            foreach ($clusterStatus as $nodeStatus) {
                if ($nodeStatus === 'connected') {
                    $connectedCount++;
                }
            }

            // Получаем минимальное количество узлов для работы кластера
            $requiredQuorum = $this->redisChecker->getRequiredQuorum();

            /**
             * Формирование успешного ответа
             *
             * Создаем структурированный JSON ответ с полной информацией о состоянии
             * всех компонентов системы. Этот ответ используется для:
             * - Мониторинга состояния на frontend dashboard
             * - Автоматических проверок здоровья (health checks)
             * - Диагностики проблем при инцидентах
             * - Логирования и аудита состояния системы
             *
             * Структура ответа:
             * - status: общий статус приложения ('OK' или 'ERROR')
             * - service: идентификатор сервиса для корреляции логов
             * - version: версия приложения для отслеживания deploys
             * - timestamp: время генерации отчета в ISO 8601 формате
             * - server: имя сервера для идентификации в кластере приложений
             * - redis_cluster: краткий статус подключения к Redis
             * - redis_details: детальная информация о состоянии кластера
             */
            echo json_encode([
                'status' => 'OK',
                'service' => 'email-validator',
                'version' => '1.0.0',
                'timestamp' => date('c'), // ISO 8601 формат для международной совместимости
                'server' => gethostname(), // Имя сервера для идентификации в кластере
                'redis_cluster' => $redisConnected ? 'connected' : 'disconnected',
                'redis_details' => [
                    'cluster_status' => $redisConnected ? 'healthy' : 'unhealthy',
                    'connected_nodes' => $connectedCount,
                    'total_nodes' => $totalNodes,
                    'quorum_required' => $requiredQuorum,
                    'quorum_met' => $connectedCount >= $requiredQuorum,
                    'nodes' => $clusterStatus // Детальный статус каждого узла
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        } catch (Throwable $e) {
            /**
             * Обработка критических ошибок
             *
             * При возникновении любых исключений во время проверки состояния
             * Redis Cluster мы возвращаем HTTP 500 с детальной информацией об ошибке.
             *
             * Используем \Throwable (а не \Exception) для максимального покрытия
             * возможных ошибок, включая:
             * - RedisClusterException при проблемах с подключением
             * - TypeError при некорректных данных конфигурации
             * - ParseError при проблемах с кодом
             * - Error при критических системных ошибках
             *
             * Структура ответа об ошибке включает:
             * - Стандартизированный формат для автоматической обработки
             * - Детальное сообщение об ошибке для разработчиков
             * - Код ошибки для категоризации проблем
             * - Информацию о статусе Redis для контекста
             */
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Internal server error',
                'error_code' => 'INTERNAL_ERROR',
                'redis_cluster' => 'disconnected',
                'redis_details' => [
                    'cluster_status' => 'error',
                    'error' => $e->getMessage() // Детальное сообщение для отладки
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }

    /**
     * Устанавливает HTTP заголовки для JSON API ответов
     *
     * Настраивает стандартные заголовки для RESTful health check API:
     * - Content-Type для JSON с правильной кодировкой UTF-8
     * - CORS заголовки для поддержки кроссдоменных запросов от frontend
     * - Разрешенные HTTP методы (GET, OPTIONS для health checks)
     * - Разрешенные заголовки для preflight запросов
     *
     * Эти заголовки критически важны для:
     * - Корректного отображения данных в браузере
     * - Интеграции с frontend приложениями
     * - Поддержки мониторинговых систем
     * - Соответствия стандартам REST API
     *
     * @return void
     */
    private function setJsonHeaders(): void
    {
        // Указываем тип содержимого и кодировку для корректного отображения
        header('Content-Type: application/json; charset=utf-8');

        // Настраиваем CORS для работы с frontend приложениями
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }

    /**
     * Фабричный метод для создания контроллера с настройками по умолчанию
     *
     * Предоставляет удобный способ создания полностью настроенного контроллера
     * мониторинга Redis без необходимости явного конструирования зависимостей.
     * Использует стандартную конфигурацию Redis из файла конфигурации.
     *
     * Этот метод упрощает инициализацию в точке входа приложения (index.php)
     * и обеспечивает консистентность настроек во всем приложении.
     *
     * @return RedisHealthController Готовый к использованию экземпляр контроллера
     */
    public static function createDefault(): RedisHealthController
    {
        return new self(new RedisHealthChecker());
    }
}