<?php

declare(strict_types=1);

namespace App\Validators;

use App\Interfaces\PartsValidatorInterface;
use App\Interfaces\ValidatorInterface;
use App\Models\ValidationResult;

/**
 * Валидатор синтаксиса email адресов согласно RFC 5322
 */
class SyntaxValidator implements ValidatorInterface, PartsValidatorInterface
{
    public function validateParts(string $localPart, string $domainPart, string $fullEmail): ValidationResult
    {
        $localValidation = $this->validateLocalPart($localPart);
        if (!$localValidation['valid']) {
            return ValidationResult::invalidFormat($fullEmail, $localValidation['reason']);
        }

        $domainValidation = $this->validateDomainPart($domainPart);
        if (!$domainValidation['valid']) {
            return ValidationResult::invalidFormat($fullEmail, $domainValidation['reason']);
        }

        if (!filter_var($fullEmail, FILTER_VALIDATE_EMAIL)) {
            return ValidationResult::invalidFormat($fullEmail, 'Email не соответствует стандартному формату');
        }

        return ValidationResult::valid($fullEmail);
    }

    public function validate(string $email): ValidationResult
    {
        if (empty(trim($email))) {
            return ValidationResult::invalidFormat($email, 'Email адрес не может быть пустым');
        }

        if (substr_count($email, '@') !== 1) {
            return ValidationResult::invalidFormat($email, 'Email должен содержать ровно один символ @');
        }

        [$localPart, $domainPart] = explode('@', $email, 2);

        return $this->validateParts($localPart, $domainPart, $email);
    }

    private function validateLocalPart(string $localPart): array
    {
        if (empty($localPart)) {
            return ['valid' => false, 'reason' => 'Локальная часть email не может быть пустой'];
        }

        if (strlen($localPart) > 64) {
            return ['valid' => false, 'reason' => 'Локальная часть email не может быть длиннее 64 символов'];
        }

        if (str_contains($localPart, '..')) {
            return ['valid' => false, 'reason' => 'Локальная часть не может содержать подряд идущие точки'];
        }

        if ($localPart[0] === '.' || $localPart[-1] === '.') {
            return ['valid' => false, 'reason' => 'Локальная часть не может начинаться или заканчиваться точкой'];
        }

        return ['valid' => true, 'reason' => ''];
    }

    private function validateDomainPart(string $domainPart): array
    {
        if (empty($domainPart)) {
            return ['valid' => false, 'reason' => 'Доменная часть email не может быть пустой'];
        }

        if (strlen($domainPart) > 253) {
            return ['valid' => false, 'reason' => 'Доменная часть email не может быть длиннее 253 символов'];
        }

        if (str_contains($domainPart, '..')) {
            return ['valid' => false, 'reason' => 'Доменная часть не может содержать подряд идущие точки'];
        }

        if ($domainPart[0] === '.' || $domainPart[-1] === '.') {
            return ['valid' => false, 'reason' => 'Доменная часть не может начинаться или заканчиваться точкой'];
        }

        if (!str_contains($domainPart, '.')) {
            return ['valid' => false, 'reason' => 'Доменная часть должна содержать как минимум одну точку'];
        }

        return ['valid' => true, 'reason' => ''];
    }
}