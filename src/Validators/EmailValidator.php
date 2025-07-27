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
    public function validate(array $email): ValidationResult
    {
        $emailString = $email['full'] ?? '';
        
        $syntaxResult = $this->syntaxValidator->validate($emailString);
        if (!$syntaxResult->isValid()) {
            return $syntaxResult;
        }

        $tldResult = $this->tldValidator->validate($emailString);
        if (!$tldResult->isValid()) {
            return $tldResult;
        }

        $mxResult = $this->mxValidator->validate($emailString);
        if (!$mxResult->isValid()) {
            return $mxResult;
        }

        return ValidationResult::valid($emailString);
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
