<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\ValidationResult;

/**
 * Базовый интерфейс для валидаторов email
 * Обеспечивает обратную совместимость с существующим API
 */
interface ValidatorInterface
{
    /**
     * Валидирует email адрес
     *
     * @param string $email Email адрес для валидации
     * @return ValidationResult Результат валидации, содержащий статус и причину
     */
    public function validate(string $email): ValidationResult;
}
