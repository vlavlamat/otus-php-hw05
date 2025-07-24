<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Класс для унифицированных JSON ответов
 *
 * Инкапсулирует всю логику формирования и отправки JSON ответов.
 * Обеспечивает консистентность HTTP заголовков и форматирования.
 */
class JsonResponse
{
    private array $data;
    private int $statusCode;
    private array $headers;

    /**
     * @param array $data Данные для отправки
     * @param int $statusCode HTTP статус код
     * @param array $headers Дополнительные заголовки
     */
    public function __construct(array $data, int $statusCode = 200, array $headers = [])
    {
        $this->data = $data;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Отправляет JSON ответ
     *
     * @return void
     */
    public function send(): void
    {
        // Устанавливаем HTTP статус
        http_response_code($this->statusCode);

        // Устанавливаем основные заголовки
        header('Content-Type: application/json; charset=utf-8');

        // Устанавливаем дополнительные заголовки
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Отправляем JSON
        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Создает успешный ответ
     *
     * @param array $data
     * @return static
     */
    public static function success(array $data): static
    {
        return new static($data, 200);
    }

    /**
     * Создает ответ с ошибкой
     *
     * @param string $message
     * @param string $code
     * @param int $statusCode
     * @return static
     */
    public static function error(string $message, string $code = 'ERROR', int $statusCode = 400): static
    {
        return new static([
            'error' => [
                'message' => $message,
                'code' => $code
            ]
        ], $statusCode);
    }

    /**
     * Создает ответ для статуса системы
     *
     * @param string $status
     * @param array $details
     * @return static
     */
    public static function status(string $status, array $details = []): static
    {
        return new static([
            'status' => $status,
            'timestamp' => date('c'),
            ...$details
        ]);
    }
}