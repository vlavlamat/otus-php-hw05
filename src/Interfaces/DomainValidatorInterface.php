<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\ValidationResult;

/**
 * Интерфейс для валидаторов домена
 * Специализируется только на валидации доменной части
 */
interface DomainValidatorInterface
{
    /**
     * Валидирует только доменную часть email
     *
     * @param string $domain Доменная часть для валидации
     * @param string $fullEmail Полный email адрес для контекста в сообщениях об ошибках
     * @return ValidationResult Результат валидации
     */
    public function validateDomain(string $domain, string $fullEmail): ValidationResult;
}
