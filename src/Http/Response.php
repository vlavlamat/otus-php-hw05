<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Класс для представления HTTP-ответа
 *
 * Инкапсулирует содержимое ответа, код статуса и заголовки,
 * предоставляя удобный интерфейс для отправки HTTP-ответа клиенту.
 */
class Response
{
    /** @var string Содержимое HTTP-ответа */
    private string $content;
    /** @var int HTTP код статуса ответа */
    private int $statusCode;
    /** @var array<string, string> Ассоциативный массив HTTP заголовков */
    private array $headers;

    /**
     * Создает новый экземпляр HTTP-ответа
     *
     * @param string $content Содержимое ответа (по умолчанию пустая строка)
     * @param int $statusCode HTTP код статуса (по умолчанию 200 - OK)
     * @param array<string, string> $headers Ассоциативный массив заголовков (по умолчанию пустой)
     */
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Отправляет HTTP-ответ клиенту
     */
    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}