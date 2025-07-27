<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Validators\EmailValidator;
use PHPUnit\Framework\TestCase;

/**
 * Базовый класс для интеграционных тестов EmailValidator
 *
 * Содержит общие методы и утилиты для тестирования валидации email
 */
abstract class BaseEmailValidationTest extends TestCase
{
    protected EmailValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = EmailValidator::createDefault();
    }

    /**
     * Парсит email-строку в массив, ожидаемый валидатором.
     *
     * @param string $email
     * @return array{local: string, domain: string, full: string}
     */
    protected function parseEmailString(string $email): array
    {
        $parts = explode('@', $email, 2);
        return [
            'local' => $parts[0] ?? '',
            'domain' => $parts[1] ?? '',
            'full' => $email
        ];
    }
}