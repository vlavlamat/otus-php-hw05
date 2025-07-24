<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Redis\Health\RedisHealthChecker;
use Throwable;

/**
 * Контроллер для мониторинга состояния Redis Cluster
 *
 * Предоставляет информацию о здоровье Redis кластера для мониторинга
 */
class RedisHealthController
{
    private RedisHealthChecker $redisChecker;

    public function __construct(RedisHealthChecker $redisChecker)
    {
        $this->redisChecker = $redisChecker;
    }

    /**
     * Возвращает статус Redis Cluster
     */
    public function getStatus(): void
    {
        try {
            // Получаем базовую информацию о состоянии Redis Cluster
            $redisConnected = $this->redisChecker->isConnected();
            $clusterStatus = $this->redisChecker->getClusterStatus();

            // Подсчет статистики узлов кластера
            $connectedCount = 0;
            $totalNodes = count($clusterStatus);

            foreach ($clusterStatus as $nodeStatus) {
                if ($nodeStatus === 'connected') {
                    $connectedCount++;
                }
            }

            // Получаем минимальное количество узлов для работы кластера
            $requiredQuorum = $this->redisChecker->getRequiredQuorum();


            // Формирование успешного ответа
            $statusData = [
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
            ];

            JsonResponse::status('OK', $statusData)->send();

        } catch (Throwable $e) {
            JsonResponse::status('error', [
                'message' => 'Internal server error',
                'error_code' => 'INTERNAL_ERROR',
                'redis_cluster' => 'disconnected',
                'redis_details' => [
                    'cluster_status' => 'error',
                    'error' => $e->getMessage() // Детальное сообщение для отладки
                ]
            ])->send();
        }
    }

    /**
     * Фабричный метод для создания контроллера с настройками по умолчанию
     */
    public static function createDefault(): RedisHealthController
    {
        return new self(new RedisHealthChecker());
    }
}