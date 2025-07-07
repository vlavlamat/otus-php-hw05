<?php

declare(strict_types=1);

/**
 * Главная точка входа для Email Validator API
 *
 * Этот файл является основным контроллером приложения, который:
 * - Настраивает автозагрузку классов через Composer
 * - Инициализирует маршрутизацию и контроллеры
 * - Обрабатывает HTTP запросы и возвращает JSON ответы
 * - Управляет CORS политиками для cross-origin запросов
 * - Предоставляет эндпоинты для валидации email и мониторинга статуса
 */

// Подключаем автозагрузчик Composer
// Автозагрузчик необходим для автоматического подключения всех классов приложения
require __DIR__ . '/../vendor/autoload.php';

use App\Router;
use App\EmailController;
use App\RedisHealthChecker;

/**
 * Настройка HTTP заголовков для JSON API
 *
 * Устанавливаем стандартные заголовки для RESTful API:
 * - Content-Type: указываем, что возвращаем JSON
 * - Access-Control-*: настраиваем CORS для работы с фронтенд приложениями
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * Обработка preflight запросов (OPTIONS)
 *
 * Браузеры отправляют OPTIONS запросы перед основными запросами
 * для проверки CORS политик. Отвечаем успешно и завершаем выполнение.
 */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Инициализация сессий с поддержкой Redis Cluster
 *
 * session_start() автоматически использует настроенный Redis Cluster
 * для хранения сессий. Это обеспечивает масштабируемость и отказоустойчивость.
 */
session_start(); // ← автоматически использует RedisCluster!

/**
 * Инициализация основных компонентов приложения
 *
 * Создаем экземпляры основных классов:
 * - Router: для маршрутизации HTTP запросов
 * - EmailController: для обработки валидации email адресов
 */
$router = new Router();
$emailController = EmailController::createDefault();

/**
 * Регистрация маршрута для валидации email адресов
 *
 * POST /verify - основной эндпоинт для валидации email
 * Принимает JSON с email адресом и возвращает результат валидации
 */
$router->addRoute('POST', '/verify', [$emailController, 'verify']);

/**
 * Регистрация маршрута для проверки статуса системы
 *
 * GET /status - эндпоинт для мониторинга состояния сервиса
 * Возвращает информацию о:
 * - Общем статусе приложения
 * - Состоянии Redis Cluster
 * - Статистике подключенных узлов
 * - Соответствии кворуму для отказоустойчивости
 */
$router->addRoute('GET', '/status', function () {
    try {
        // Создаем объект, который проверяет состояние Redis
        $redisChecker = new RedisHealthChecker();

        // Получаем базовую информацию о подключении
        $redisConnected = $redisChecker->isConnected();
        $clusterStatus = $redisChecker->getClusterStatus();

        /**
         * Подсчет статистики узлов кластера
         *
         * Анализируем состояние каждого узла Redis Cluster
         * для определения общего здоровья кластера
         */
        $connectedCount = 0;
        $totalNodes = count($clusterStatus);
        foreach ($clusterStatus as $nodeStatus) {
            if ($nodeStatus === 'connected') {
                $connectedCount++;
            }
        }

        // Получаем минимальное количество узлов для работы кластера
        $requiredQuorum = $redisChecker->getRequiredQuorum();

        /**
         * Формирование успешного ответа
         *
         * Возвращаем структурированную информацию о состоянии всех компонентов:
         * - service: название сервиса
         * - version: версия приложения
         * - timestamp: время генерации отчета в ISO 8601
         * - server: имя сервера для идентификации в кластере
         * - redis_cluster: общий статус подключения
         * - redis_details: детальная информация о кластере
         */
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
        /**
         * Обработка критических ошибок
         *
         * При возникновении любых исключений возвращаем HTTP 500
         * с информацией об ошибке. Используем \Throwable для
         * максимального покрытия возможных ошибок.
         */
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

/**
 * Диспетчеризация HTTP запросов
 *
 * Получаем информацию о текущем запросе из суперглобальных переменных
 * и передаем в роутер для обработки соответствующим контроллером.
 *
 * Поддерживаемые методы: GET, POST, OPTIONS
 * Все неизвестные маршруты будут обработаны роутером с возвратом 404
 */
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Запускаем маршрутизацию и обработку запроса
$router->dispatch($method, $uri);