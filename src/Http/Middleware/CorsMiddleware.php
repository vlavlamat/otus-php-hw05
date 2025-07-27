<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;

/**
 * Middleware для обработки CORS заголовков
 *
 * Отвечает исключительно за установку HTTP заголовков для кроссдоменных запросов.
 * Применяет принцип единственной ответственности - только CORS, ничего больше.
 */
class CorsMiddleware
{
    /**
     * Устанавливает CORS заголовки для всех запросов
     *
     * @return void
     */
    public function handle(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400'); // Кэширование preflight на 24 часа
    }

    /**
     * Обрабатывает OPTIONS запросы (preflight)
     *
     * @return Response
     */

    public function handlePreflight(): Response
    {
        return new Response(
            '',
            200,
            [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Access-Control-Max-Age' => '86400'

            ]
        );
    }

    /**
     * Проверяет, является ли запрос preflight
     *
     * @return bool
     */
    public function isPreflight(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'OPTIONS'
            && isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);
    }
}