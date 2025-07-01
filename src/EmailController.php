<?php

declare(strict_types=1);

namespace App;

use Exception;
use Throwable;

/**
 * Класс EmailController
 *
 * Контроллер для обработки HTTP-запросов по проверке email-адресов
 */
class EmailController
{
    private EmailExtractor $emailExtractor;
    private EmailValidator $emailValidator;
    private ValidationRequest $validationRequest;

    /**
     * Конструктор контроллера
     */
    public function __construct()
    {
        $this->emailExtractor = new EmailExtractor();
        $this->emailValidator = new EmailValidator();
        $this->validationRequest = new ValidationRequest();
    }

    /**
     * Обрабатывает запрос на проверку email-адресов
     *
     * @return void
     */
    public function validateEmails(): void
    {
        // Устанавливаем заголовки для JSON-ответа
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        // Обработка preflight-запроса для CORS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Проверяем метод запроса
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Метод не поддерживается', 405);
            return;
        }

        try {
            // Получаем данные из запроса
            $inputData = $this->getRequestData();

            // Валидируем входящие данные
            $validation = $this->validationRequest->validate($inputData);

            if (!$validation['valid']) {
                $this->sendError($validation['errors']);
                return;
            }

            // Получаем текст для обработки
            $text = $this->validationRequest->getText($validation);

            // Извлекаем email-адреса из текста
            $emails = $this->emailExtractor->extractEmails($text);

            // Если email-адреса не найдены
            if (empty($emails)) {
                $this->sendSuccess([
                    'message' => 'Email-адреса в тексте не найдены',
                    'emails' => [],
                    'total_found' => 0
                ]);
                return;
            }

            // Валидируем найденные email-адреса
            $validationResults = $this->emailValidator->validate($emails);

            // Формируем ответ
            $this->sendSuccess([
                'message' => 'Обработка завершена успешно',
                'emails' => $validationResults,
                'total_found' => count($validationResults),
                'statistics' => $this->getStatistics($validationResults)
            ]);

        } catch (Throwable) {
            $this->sendError('Внутренняя ошибка сервера', 500);
        }
    }

    /**
     * Получает данные из HTTP-запроса
     *
     * @return array Данные запроса
     * @throws Exception Если данные не могут быть получены
     */
    private function getRequestData(): array
    {
        $rawInput = file_get_contents('php://input');

        if ($rawInput === false) {
            throw new Exception('Не удалось получить данные запроса');
        }

        $data = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Некорректный JSON в запросе');
        }

        return $data ?? [];
    }

    /**
     * Отправляет успешный ответ
     *
     * @param array $data Данные ответа
     * @return void
     */
    private function sendSuccess(array $data): void
    {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Отправляет ответ с ошибкой
     *
     * @param array|string $message Сообщение об ошибке
     * @param int $code HTTP-код ошибки
     * @return void
     */
    private function sendError(array|string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Вычисляет статистику по результатам валидации
     *
     * @param array $results Результаты валидации
     * @return array Статистика (упрощенная версия: только valid/invalid)
     */
    private function getStatistics(array $results): array
    {
        $stats = [
            'valid' => 0,
            'invalid' => 0
        ];

        foreach ($results as $result) {
            $status = $result['status'] ?? 'unknown';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }
}
