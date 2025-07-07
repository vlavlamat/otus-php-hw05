<?php

declare(strict_types=1);

namespace App;

/**
 * Объект передачи данных для результатов валидации
 * Хранит email, статус валидации и причину неудачной валидации
 */
class ValidationResult
{
    /**
     * @param string $email Email адрес, который был валидирован
     * @param string $status Статус валидации (valid, invalid_format, invalid_tld, invalid_mx)
     * @param string|null $reason Причина неудачной валидации (null если валиден)
     */
    public function __construct(
        public readonly string  $email,
        public readonly string  $status,
        public readonly ?string $reason = null
    )
    {
    }

    /**
     * Создать успешный результат валидации
     *
     * @param string $email Валидированный email адрес
     * @return ValidationResult
     */
    public static function valid(string $email): ValidationResult
    {
        return new self($email, 'valid');
    }

    /**
     * Создать результат валидации для неверного формата
     *
     * @param string $email Email адрес
     * @param string $reason Причина неверного формата
     * @return ValidationResult
     */
    public static function invalidFormat(string $email, string $reason): ValidationResult
    {
        return new self($email, 'invalid_format', $reason);
    }

    /**
     * Создать результат валидации для неверного TLD
     *
     * @param string $email Email адрес
     * @param string $reason Причина неверного TLD
     * @return ValidationResult
     */
    public static function invalidTld(string $email, string $reason): ValidationResult
    {
        return new self($email, 'invalid_tld', $reason);
    }

    /**
     * Создать результат валидации для неверной MX записи
     *
     * @param string $email Email адрес
     * @param string $reason Причина неверной MX записи
     * @return ValidationResult
     */
    public static function invalidMx(string $email, string $reason): ValidationResult
    {
        return new self($email, 'invalid_mx', $reason);
    }

    /**
     * Проверить, является ли результат валидации валидным
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    /**
     * Преобразовать в массив для JSON сериализации
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'status' => $this->status,
            'reason' => $this->reason,
        ];
    }
}
