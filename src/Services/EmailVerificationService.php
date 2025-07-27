<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ValidationResult;
use App\Validators\EmailValidator;

/**
 * Сервис для валидации email адресов
 */
class EmailVerificationService
{

    private EmailValidator $emailValidator;

    public function __construct(EmailValidator $emailValidator)
    {
        $this->emailValidator = $emailValidator;
    }

    /**
     * Валидирует массив email адресов
     */
    public function verify(array $emails): array
    {
        $results = [];

        foreach ($emails as $email) {
            // Нормализуем входные данные - приводим к строке и убираем пробелы
            $cleanEmail = is_string($email) ? trim($email) : '';

            // Проверяем, что после очистки email не пустой
            if (empty($cleanEmail)) {
                // Создаем результат с ошибкой для пустых или некорректных email
                $results[] = ValidationResult::invalidFormat($email, 'Email адрес не может быть пустым');
                continue;
            }

            // Выполняем валидацию через основной валидатор
            $results[] = $this->emailValidator->validate($cleanEmail);
        }

        return $results;
    }


    /**
     * Валидирует email и возвращает результат для API
     */
    public function verifyForApi(array $emails): array
    {
        // Получаем результаты валидации
        $results = $this->verify($emails);
        $formattedResults = [];

        // Преобразуем каждый результат в массив для JSON
        foreach ($results as $result) {
            $formattedResults[] = $result->toArray();
        }

        return $formattedResults;
    }


    /**
     * Создает сервис с валидатором по умолчанию
     */
    public static function createDefault(): EmailVerificationService
    {
        return new self(EmailValidator::createDefault());
    }

}
