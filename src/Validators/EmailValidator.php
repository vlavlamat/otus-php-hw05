<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\ValidationResult;

/**
 * Композитный валидатор email адресов
 * 
 * Объединяет синтаксическую, TLD и MX валидацию с fail-fast стратегией
 */
class EmailValidator
{
    private SyntaxValidator $syntaxValidator;
    private TldValidator $tldValidator;
    private MxValidator $mxValidator;

    public function __construct(
        SyntaxValidator $syntaxValidator,
        TldValidator    $tldValidator,
        MxValidator     $mxValidator
    )
    {
        $this->syntaxValidator = $syntaxValidator;
        $this->tldValidator = $tldValidator;
        $this->mxValidator = $mxValidator;
    }

    /**
     * Валидация email с fail-fast стратегией
     */
    public function validate(string $email): ValidationResult
    {
        $syntaxResult = $this->syntaxValidator->validate($email);
        if (!$syntaxResult->isValid()) {
            return $syntaxResult;
        }

        $tldResult = $this->tldValidator->validate($email);
        if (!$tldResult->isValid()) {
            return $tldResult;
        }

        $mxResult = $this->mxValidator->validate($email);
        if (!$mxResult->isValid()) {
            return $mxResult;
        }

        return ValidationResult::valid($email);
    }


    public static function createDefault(): EmailValidator
    {
        return new self(
            new SyntaxValidator(),
            new TldValidator(),
            new MxValidator()
        );
    }

    public function getValidators(): array
    {
        return [
            'syntax' => $this->syntaxValidator,
            'tld' => $this->tldValidator,
            'mx' => $this->mxValidator,
        ];
    }

}
