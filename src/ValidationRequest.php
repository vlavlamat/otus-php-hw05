<?php

declare(strict_types=1);


namespace App;

/**
 * Класс ValidationRequest
 *
 * Отвечает за валидацию входящих HTTP-запросов
 * и обработку данных от фронтенда
 */
class ValidationRequest
{
    /**
     * Максимальное количество символов в тексте
     */
    private const MAX_TEXT_LENGTH = 15000;

    /**
     * Валидирует входящий запрос на проверку email-адресов
     *
     * @param array $data Данные запроса
     * @return array Результат валидации
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Проверяем наличие поля text
        if (!isset($data['text'])) {
            $errors[] = 'Поле "text" обязательно для заполнения';
        }

        // Проверяем тип данных
        if (isset($data['text']) && !is_string($data['text'])) {
            $errors[] = 'Поле "text" должно быть строкой';
        }

        // Проверяем длину текста
        if (isset($data['text']) && is_string($data['text'])) {
            $textLength = mb_strlen($data['text'], 'UTF-8');
            if ($textLength > self::MAX_TEXT_LENGTH) {
                $errors[] = sprintf(
                    'Текст слишком длинный. Максимум %d символов, получено %d',
                    self::MAX_TEXT_LENGTH,
                    $textLength
                );
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $data
        ];
    }

    /**
     * Получает текст из валидированных данных
     *
     * @param array $validatedData Валидированные данные
     * @return string Текст для обработки
     */
    public function getText(array $validatedData): string
    {
        return $validatedData['data']['text'] ?? '';
    }

    /**
     * Получает максимальную длину текста
     *
     * @return int Максимальная длина
     */
    public function getMaxTextLength(): int
    {
        return self::MAX_TEXT_LENGTH;
    }
}