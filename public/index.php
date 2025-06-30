<?php

declare(strict_types=1);

// Подключаем автозагрузчик Composer
require __DIR__ . '/../vendor/autoload.php';

use App\Router;
use App\EmailController;
use App\RedisHealthChecker;

// Устанавливаем заголовки для JSON API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обрабатываем preflight запросы
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Добавим работу с сессиями
session_start(); // ← автоматически использует RedisCluster!

$router = new Router();
$emailController = new EmailController();

// Маршрут для валидации email
$router->addRoute('POST', '/verify', [$emailController, 'validateEmails']);

// Маршрут для проверки статуса Redis Cluster
$router->addRoute('GET', '/status', function () {
    try {
        $redisChecker = new RedisHealthChecker();
        $redisConnected = $redisChecker->isConnected();
        $clusterStatus = $redisChecker->getClusterStatus();

        // Подсчитываем статистику узлов
        $connectedCount = 0;
        $totalNodes = count($clusterStatus);
        foreach ($clusterStatus as $nodeStatus) {
            if ($nodeStatus === 'connected') {
                $connectedCount++;
            }
        }

        // Получаем требуемый кворум из конфигурации
        $requiredQuorum = $redisChecker->getRequiredQuorum();

        echo json_encode([
            'status' => 'OK',
            'service' => 'email-validator',
            'version' => '1.0.0',
            'timestamp' => date('c'),
            'server' => gethostname(),
            'redis_cluster' => $redisConnected ? 'connected' : 'disconnected',
            'redis_details' => [
                'cluster_status' => $redisConnected ? 'healthy' : 'unhealthy',
                'connected_nodes' => $connectedCount,
                'total_nodes' => $totalNodes,
                'quorum_required' => $requiredQuorum,
                'quorum_met' => $connectedCount >= $requiredQuorum,
                'nodes' => $clusterStatus
            ]
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Internal server error',
            'error_code' => 'INTERNAL_ERROR',
            'redis_cluster' => 'disconnected',
            'redis_details' => [
                'cluster_status' => 'error',
                'error' => $e->getMessage()
            ]
        ]);
    }
});

// Диспетчеризация запроса
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$router->dispatch($method, $uri);
