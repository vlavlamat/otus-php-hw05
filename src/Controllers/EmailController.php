<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\EmailVerificationService;
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
 * - Валидация массивов email адресов с детальными результатами
 * - Валидация одиночных email адресов
 * - Получение статистики по результатам валидации
 * - Настраиваемые конфигурации валидаторов
 * - Проверка работоспособности сервиса (health check)
 * - Поддержка CORS для кроссдоменных запросов
 * - Обработка ошибок с детальными сообщениями
 *
 * Архитектурные принципы:
 * - Использует dependency injection для тестируемости
 * - Все методы возвращают JSON ответы
 * - Централизованная обработка ошибок
 * - Валидация входных данных с подробными сообщениями об ошибках
 * - Лимитирование количества email для предотвращения злоупотреблений
 *
 * @package App
 * @author Vladimir Matkovskii and Claude 4 Sonnet
 * @version 1.0
 */
class EmailController
{
    /**
     * Максимальное количество email адресов для валидации за один запрос
     * Предотвращает перегрузку сервера при массовых запросах
     */
    private const MAX_EMAILS_PER_REQUEST = 1000;


    /**
     * Сервис для выполнения валидации email адресов
     * Инкапсулирует всю бизнес-логику валидации
     *
     * @var EmailVerificationService
     */
    private EmailVerificationService $verificationService;

    /**
     * Конструктор контроллера
     *
     * Инициализирует контроллер с необходимым сервисом валидации.
     * Использует dependency injection для лучшей тестируемости и гибкости.
     *
     * @param EmailVerificationService $verificationService Сервис для валидации email адресов
     */
    public function __construct(EmailVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Основной эндпоинт для валидации массива email адресов
     *
     * Принимает POST запрос с JSON, содержащим массив email адресов,
     * и возвращает результаты валидации для каждого адреса.
     *
     * Формат входных данных:
     * {
     *   "emails": ["user@example.com", "test@domain.org", ...]
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
     *   "total": 2
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

            // Валидируем структуру запроса - проверяем наличие поля 'emails'
            if (!isset($requestData['emails']) || !is_array($requestData['emails'])) {
                $this->sendErrorResponse('Поле "emails" обязательно и должно быть массивом');
                return;
            }

            $emails = $requestData['emails'];

            // Проверяем, что массив email адресов не пустой
            if (empty($emails)) {
                $this->sendErrorResponse('Массив email адресов не может быть пустым');
                return;
            }

            // Ограничиваем количество email для предотвращения злоупотреблений
            if (count($emails) > self::MAX_EMAILS_PER_REQUEST) {
                $this->sendErrorResponse(
                    'Максимальное количество email адресов для проверки: ' . self::MAX_EMAILS_PER_REQUEST
                );
                return;
            }

            // Выполняем валидацию через сервис
            $results = $this->verificationService->verifyForApi($emails);

            // Отправляем успешный ответ с результатами
            $this->sendSuccessResponse([
                'success' => true,
                'results' => $results,
                'total' => count($results),
            ]);

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
     * @throws JsonException При некорректном JSON (автоматически обрабатывается в вызывающих методах)
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
     * Фабричный метод для создания контроллера с сервисом по умолчанию
     *
     * Упрощает создание экземпляра контроллера с готовой конфигурацией.
     * Использует стандартный сервис валидации со всеми включенными валидаторами.
     *
     * @return EmailController Готовый к использованию экземпляр контроллера
     */
    public static function createDefault(): EmailController
    {
        return new self(EmailVerificationService::createDefault());
    }

}
