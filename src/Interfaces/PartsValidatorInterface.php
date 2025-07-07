<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\ValidationResult;

/**
 * Интерфейс для валидаторов частей email
 * Позволяет работать с уже разобранными частями email адреса
 */
interface PartsValidatorInterface
{
    /**
     * Валидирует части email адреса
     *
     * @param string $localPart Локальная часть email (до @)
     * @param string $domainPart Доменная часть email (после @)
     * @param string $fullEmail Полный email адрес для контекста в сообщениях об ошибках
     * @return ValidationResult Результат валидации
     */
    public function validateParts(string $localPart, string $domainPart, string $fullEmail): ValidationResult;
}
