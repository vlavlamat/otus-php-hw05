<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\EmailVerificationService;
use App\Validators\InputValidator;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * HTTP контроллер для API валидации email адресов
 *
 * Этот класс является основным HTTP контроллером, который обрабатывает
 * REST API запросы для валидации email адресов. Он служит мостом между
 * HTTP слоем и бизнес-логикой валидации.
 *
 * Основные возможности:
 * - Валидация текста с email адресами с автоматическим парсингом
 * - Поддержка различных форматов разделителей (новые строки, запятые, пробелы)
 * - Валидация входных данных на соответствие ограничениям
 * - Получение детальных результатов валидации для каждого email
 * - Проверка работоспособности сервиса (health check)
 * - Поддержка CORS для кроссдоменных запросов
 * - Обработка ошибок с детальными сообщениями
 *
 * Архитектурные принципы:
 * - Использует dependency injection для тестируемости
 * - Все методы возвращают JSON ответы
 * - Централизованная обработка ошибок
 * - Валидация входных данных с подробными сообщениями об ошибках
 * - Лимитирование размера текста и количества email для предотвращения злоупотреблений
 *
 * @package App\Controllers
 * @author Vladimir Matkovskii and Claude 4 Sonnet
 * @version 1.0
 */
class EmailController
{
    /**
     * Сервис для выполнения валидации email адресов
     * Инкапсулирует всю бизнес-логику валидации
     */
    private EmailVerificationService $verificationService;

    /**
     * Валидатор входных данных
     * Проверяет соответствие данных ограничениям системы
     */
    private InputValidator $inputValidator;

    /**
     * Конструктор контроллера
     *
     * Инициализирует контроллер с необходимыми сервисами для валидации.
     * Использует dependency injection для лучшей тестируемости и гибкости.
     *
     * @param EmailVerificationService $verificationService Сервис для валидации email адресов
     * @param InputValidator $inputValidator Валидатор входных данных
     */
    public function __construct(
        EmailVerificationService $verificationService,
        InputValidator $inputValidator
    ) {
        $this->verificationService = $verificationService;
        $this->inputValidator = $inputValidator;
    }

    /**
     * Основной эндпоинт для валидации email адресов из текста
     *
     * Принимает POST запрос с JSON, содержащим текст с email адресами,
     * парсит их и возвращает результаты валидации для каждого адреса.
     *
     * Формат входных данных:
     * {
     *   "text": "user@example.com, test@domain.org\nuser2@test.com user3@site.ru"
     * }
     *
     * Формат ответа:
     * {
     *   "success": true,
     *   "results": [
     *     {
     *       "email": "user@example.com",
     *       "valid": true,
     *       "reason": null,
     *       "validations": {...}
     *     },
     *     ...
     *   ],
     *   "total": 4,
     *   "parsed_count": 4,
     *   "original_text": "user@example.com, test@domain.org..."
     * }
     *
     * @return void Отправляет JSON ответ напрямую через HTTP
     */
    public function verify(): void
    {
        try {
            // Настраиваем HTTP заголовки для JSON API
            $this->setJsonHeaders();

            // Получаем и парсим данные из тела запроса
            $requestData = $this->getRequestData();

            // Валидируем структуру запроса - проверяем наличие поля 'text'
            if (!isset($requestData['text']) || !is_string($requestData['text'])) {
                $this->sendErrorResponse('Поле "text" обязательно и должно быть строкой');
                return;
            }

            $inputText = $requestData['text'];

            // Валидируем длину входного текста
            $this->inputValidator->validateTextLength($inputText);

            // Парсим email адреса из входного текста
            $emails = $this->parseEmails($inputText);

            // Валидируем количество найденных email адресов
            $this->inputValidator->validateArraySize($emails, 'email адресов');

            // Выполняем валидацию через сервис
            $results = $this->verificationService->verifyForApi($emails);

            // Отправляем успешный ответ с результатами и дополнительной информацией
            $this->sendSuccessResponse([
                'success' => true,
                'results' => $results,
                'total' => count($results),
                'parsed_count' => count($emails),
                'original_text' => $this->truncateText($inputText)
            ]);

        } catch (InvalidArgumentException $e) {
            // Обрабатываем ошибки валидации входных данных
            $this->sendErrorResponse($e->getMessage());
        } catch (JsonException $e) {
            // Обрабатываем ошибки парсинга JSON
            $this->sendErrorResponse('Некорректный JSON в запросе: ' . $e->getMessage());
        } catch (Throwable $e) {
            // Обрабатываем любые непредвиденные ошибки
            $this->sendErrorResponse('Внутренняя ошибка сервера: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Получает и парсит данные из тела HTTP запроса
     *
     * Читает JSON из входящего запроса и преобразует в PHP массив.
     * Обрабатывает ошибки парсинга JSON и возвращает пустой массив
     * для пустых запросов.
     *
     * @return array Декодированные данные из JSON или пустой массив
     * @throws JsonException При некорректном JSON
     */
    private function getRequestData(): array
    {
        // Читаем сырые данные из входящего запроса
        $input = file_get_contents('php://input');

        // Возвращаем пустой массив для пустых запросов
        if (empty($input)) {
            return [];
        }

        // Парсим JSON с включенным исключением при ошибках
        $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);

        // Гарантируем, что возвращаем массив (на случай, если JSON содержит не объект)
        return is_array($data) ? $data : [];
    }

    /**
     * Устанавливает HTTP заголовки для JSON API ответов
     *
     * Настраивает стандартные заголовки для RESTful API:
     * - Content-Type для JSON с правильной кодировкой
     * - CORS заголовки для поддержки кроссдоменных запросов
     * - Разрешенные HTTP методы и заголовки
     *
     * @return void
     */
    private function setJsonHeaders(): void
    {
        // Указываем тип содержимого и кодировку
        header('Content-Type: application/json; charset=utf-8');

        // Настраиваем CORS для работы с фронтенд приложениями
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }

    /**
     * Отправляет успешный JSON ответ
     *
     * Формирует и отправляет HTTP ответ с кодом 200 и JSON данными.
     * Использует красивое форматирование JSON для удобства отладки.
     *
     * @param array $data Данные для включения в JSON ответ
     * @return void
     */
    private function sendSuccessResponse(array $data): void
    {
        // Устанавливаем код успешного ответа
        http_response_code(200);

        // Отправляем JSON с красивым форматированием и поддержкой Unicode
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Отправляет JSON ответ с ошибкой
     *
     * Формирует и отправляет HTTP ответ с кодом ошибки и детальным
     * описанием проблемы. Включает временную метку для логирования.
     *
     * @param string $message Сообщение об ошибке для пользователя
     * @param int $statusCode HTTP код ошибки (по умолчанию 400 Bad Request)
     * @return void
     */
    private function sendErrorResponse(string $message, int $statusCode = 400): void
    {
        // Устанавливаем соответствующий код ошибки
        http_response_code($statusCode);

        // Формируем структурированный ответ об ошибке
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c'), // ISO 8601 формат для логирования
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Обрезает текст до фиксированной длины для включения в ответ
     *
     * Полезно для включения фрагмента исходного текста в ответ API
     * без передачи всего содержимого.
     *
     * @param string $text Исходный текст
     * @return string Обрезанный текст с многоточием при необходимости
     */
    private function truncateText(string $text): string
    {
        $maxLength = 100;

        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength - 3) . '...';
    }

    /**
     * Парсит текст с email адресами в массив
     *
     * Разбивает входной текст по всем поддерживаемым разделителям,
     * нормализует данные и возвращает очищенный массив уникальных email адресов.
     *
     * Поддерживаемые разделители:
     * - Переносы строк (\n, \r\n)
     * - Запятая с пробелом (", ")
     * - Точка с запятой с пробелом ("; ")
     * - Запятая перед переносом строки (",\n", ";\n")
     * - Одиночная запятая (",")
     * - Точка с запятой (";")
     * - Пробелы (" ")
     *
     * @param string $text Входной текст с email адресами
     * @return array Массив уникальных email адресов
     */
    private function parseEmails(string $text): array
    {
        // Разделители для парсинга email адресов в порядке приоритета
        $separators = [
            "\r\n",  // Windows переносы строк (должен быть перед \n)
            "\n",    // Unix/Linux переносы строк
            ",\n",   // Запятая перед переносом строки
            ";\n",   // Точка с запятой перед переносом строки
            ", ",    // Запятая с пробелом (стандартный CSV формат)
            "; ",    // Точка с запятой с пробелом
            ",",     // Одиночная запятая
            ";",     // Точка с запятой
            " ",     // Пробелы (обрабатывается последним)
        ];

        // Начинаем с исходного текста как единого элемента
        $items = [$text];

        // Последовательно разбиваем по каждому разделителю
        foreach ($separators as $separator) {
            $newItems = [];
            foreach ($items as $item) {
                if (is_string($item) && str_contains($item, $separator)) {
                    $splitItems = explode($separator, $item);
                    $newItems = array_merge($newItems, $splitItems);
                } else {
                    $newItems[] = $item;
                }
            }
            $items = $newItems;
        }

        // Нормализуем данные: trim + фильтрация пустых + удаление дубликатов
        $cleaned = [];
        foreach ($items as $item) {
            $cleanItem = trim((string)$item);
            $cleanItem = trim($cleanItem, ",; \n\r");
            if ($cleanItem !== '') {
                $cleaned[] = $cleanItem;
            }
        }

        return array_values(array_unique($cleaned));
    }

    /**
     * Фабричный метод для создания контроллера с сервисами по умолчанию
     *
     * Упрощает создание экземпляра контроллера с готовой конфигурацией.
     * Использует стандартные сервисы с настройками по умолчанию.
     *
     * @return EmailController Готовый к использованию экземпляр контроллера
     */
    public static function createDefault(): EmailController
    {
        return new self(
            EmailVerificationService::createDefault(),
            new InputValidator()
        );
    }
}
