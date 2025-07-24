<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;
use App\Controllers\EmailController;
use App\Http\Middleware\CorsMiddleware;
use App\Controllers\RedisHealthController;

/**
 * Основной класс приложения
 *
 * Инициализирует компоненты приложения, обрабатывает входящие HTTP-запросы
 * и предоставляет централизованную обработку ошибок.
 */
class App
{
    private Router $router;
    private CorsMiddleware $corsMiddleware;

    public function __construct()
    {
        $this->corsMiddleware = new CorsMiddleware();
        $this->router = new Router();
        $this->setupRoutes();
    }

    /**
     * Запускает приложение
     *
     * Обрабатывает CORS, инициализирует сессии и передает управление роутеру.
     * В случае критических ошибок выполняет централизованную обработку исключений.
     */
    public function run(): void
    {
        try {
            // Обработка CORS preflight
            if ($this->corsMiddleware->isPreflight()) {
                $response = $this->corsMiddleware->handlePreflight();
                $response->send();
                return;
            }

            // Установка CORS заголовков
            $this->corsMiddleware->handle();

            // Старт сессии Redis Cluster
            session_start();

            // Обработка запроса
            $method = $_SERVER['REQUEST_METHOD'];
            $uri = $_SERVER['REQUEST_URI'];

            $this->router->dispatch($method, $uri);

        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Регистрирует маршруты приложения
     */
    private function setupRoutes(): void
    {
        $emailController = EmailController::createDefault();
        $healthController = RedisHealthController::createDefault();

        $this->router->addRoute('POST', '/verify', [$emailController, 'verify']);
        $this->router->addRoute('GET', '/status', [$healthController, 'getStatus']);
    }

    /**
     * Централизованная обработка критических ошибок приложения
     *
     * @param Throwable $e Исключение для обработки
     */
    private function handleException(Throwable $e): void
    {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');

        $error = [
            'error' => [
                'message' => 'Internal server error',
                'code' => 'INTERNAL_ERROR',
            ]
        ];

        // В dev режиме добавляем отладочную информацию
        if (getenv('APP_ENV') === 'development') {
            $error['debug'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }
        echo json_encode($error, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}