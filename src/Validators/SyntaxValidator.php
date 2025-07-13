<?php

declare(strict_types=1);

namespace App\Validators;

use App\Interfaces\PartsValidatorInterface;
use App\Interfaces\ValidatorInterface;
use App\Models\ValidationResult;

/**
 * Валидатор синтаксиса email адресов согласно RFC 5322
 *
 * Обеспечивает валидацию email адресов на соответствие базовым синтаксическим правилам,
 * включая проверку локальной части (до @) и доменной части (после @).
 * Работает как с полными email адресами, так и с уже разобранными частями.
 *
 * @package App\Validators
 * @authors  Vladimir Matkovskii and Claude 4 Sonnet
 * @version 1.0
 */
class SyntaxValidator implements ValidatorInterface, PartsValidatorInterface
{
    /**
     * Валидирует части email адреса
     *
     * Основной метод для валидации уже разобранных частей email адреса.
     * Проверяет локальную часть, доменную часть и использует встроенный
     * PHP фильтр для дополнительной валидации.
     *
     * @param string $localPart Локальная часть email (до символа @)
     * @param string $domainPart Доменная часть email (после символа @)
     * @param string $fullEmail Полный email адрес для включения в результат
     *
     * @return ValidationResult Результат валидации
     */
    public function validateParts(string $localPart, string $domainPart, string $fullEmail): ValidationResult
    {
        // Валидация локальной части
        $localValidation = $this->validateLocalPart($localPart);
        if (!$localValidation['valid']) {
            return ValidationResult::invalidFormat($fullEmail, $localValidation['reason']);
        }

        // Валидация доменной части
        $domainValidation = $this->validateDomainPart($domainPart);
        if (!$domainValidation['valid']) {
            return ValidationResult::invalidFormat($fullEmail, $domainValidation['reason']);
        }

        // Использование встроенного фильтра PHP как дополнительной валидации
        if (!filter_var($fullEmail, FILTER_VALIDATE_EMAIL)) {
            return ValidationResult::invalidFormat($fullEmail, 'Email не соответствует стандартному формату');
        }

        return ValidationResult::valid($fullEmail);
    }

    /**
     * Валидирует email адрес (для обратной совместимости)
     *
     * Выполняет базовую проверку и разбор email адреса на части,
     * затем делегирует валидацию методу validateParts().
     *
     * @param string $email Email адрес для валидации
     *
     * @return ValidationResult Результат валидации
     */
    public function validate(string $email): ValidationResult
    {
        // Базовая проверка на пустоту после удаления пробелов
        if (empty(trim($email))) {
            return ValidationResult::invalidFormat($email, 'Email адрес не может быть пустым');
        }

        // Проверка на наличие ровно одного символа @
        if (substr_count($email, '@') !== 1) {
            return ValidationResult::invalidFormat($email, 'Email должен содержать ровно один символ @');
        }

        // Разделение email на локальную и доменную части
        [$localPart, $domainPart] = explode('@', $email, 2);

        // Делегирование валидации частей
        return $this->validateParts($localPart, $domainPart, $email);
    }

    /**
     * Валидирует локальную часть email адреса (до символа @)
     *
     * Проверяет соответствие локальной части следующим правилам:
     * - Не может быть пустой
     * - Не может превышать 64 символа
     * - Не может содержать подряд идущие точки
     * - Не может начинаться или заканчиваться точкой
     *
     * @param string $localPart Локальная часть для валидации
     *
     * @return array Ассоциативный массив с ключами 'valid' (bool) и 'reason' (string)
     */
    private function validateLocalPart(string $localPart): array
    {
        // Проверка на пустоту
        if (empty($localPart)) {
            return ['valid' => false, 'reason' => 'Локальная часть email не может быть пустой'];
        }

        // Проверка длины (максимум 64 символа согласно RFC 5321)
        if (strlen($localPart) > 64) {
            return ['valid' => false, 'reason' => 'Локальная часть email не может быть длиннее 64 символов'];
        }

        // Проверка на две последовательные точки
        if (str_contains($localPart, '..')) {
            return ['valid' => false, 'reason' => 'Локальная часть не может содержать подряд идущие точки'];
        }

        // Проверка на начальные или конечные точки
        if ($localPart[0] === '.' || $localPart[-1] === '.') {
            return ['valid' => false, 'reason' => 'Локальная часть не может начинаться или заканчиваться точкой'];
        }

        return ['valid' => true, 'reason' => ''];
    }

    /**
     * Валидирует доменную часть email адреса (после символа @)
     *
     * Проверяет соответствие доменной части следующим правилам:
     * - Не может быть пустой
     * - Не может превышать 253 символа
     * - Не может содержать подряд идущие точки
     * - Не может начинаться или заканчиваться точкой
     * - Должна содержать как минимум одну точку (для TLD)
     *
     * @param string $domainPart Доменная часть для валидации
     *
     * @return array Ассоциативный массив с ключами 'valid' (bool) и 'reason' (string)
     */
    private function validateDomainPart(string $domainPart): array
    {
        // Проверка на пустоту
        if (empty($domainPart)) {
            return ['valid' => false, 'reason' => 'Доменная часть email не может быть пустой'];
        }

        // Проверка длины (максимум 253 символа согласно RFC 1035)
        if (strlen($domainPart) > 253) {
            return ['valid' => false, 'reason' => 'Доменная часть email не может быть длиннее 253 символов'];
        }

        // Проверка на две последовательные точки
        if (str_contains($domainPart, '..')) {
            return ['valid' => false, 'reason' => 'Доменная часть не может содержать подряд идущие точки'];
        }

        // Проверка на начальные или конечные точки
        if ($domainPart[0] === '.' || $domainPart[-1] === '.') {
            return ['valid' => false, 'reason' => 'Доменная часть не может начинаться или заканчиваться точкой'];
        }

        // Доменная часть должна содержать как минимум одну точку (для разделения домена и TLD)
        if (!str_contains($domainPart, '.')) {
            return ['valid' => false, 'reason' => 'Доменная часть должна содержать как минимум одну точку'];
        }

        return ['valid' => true, 'reason' => ''];
    }
}