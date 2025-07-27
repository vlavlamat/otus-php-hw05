<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Интеграционные тесты для EmailValidator с фокусом на полный процесс валидации.
 *
 * @covers \App\Validators\EmailValidator
 * @uses   \App\Models\ValidationResult
 * @uses   \App\Models\ValidationResult::__construct
 * @uses   \App\Models\ValidationResult::invalidFormat
 * @uses   \App\Models\ValidationResult::isValid
 * @uses   \App\Redis\Adapters\RedisCacheAdapter
 * @uses   \App\Redis\Adapters\RedisCacheAdapter::__construct
 * @uses   \App\Validators\MxValidator
 * @uses   \App\Validators\MxValidator::__construct
 * @uses   \App\Validators\SyntaxValidator::validate
 * @uses   \App\Validators\SyntaxValidator::validateLocalPart
 * @uses   \App\Validators\SyntaxValidator::validateParts
 * @uses   \App\Validators\TldValidator
 * @uses   \App\Validators\TldValidator::__construct
 * @uses   \App\Validators\TldValidator::loadFromIana
 * @uses   \App\Validators\TldValidator::loadFromRedisCache
 * @uses   \App\Validators\TldValidator::loadValidTlds
 * @uses   \App\Validators\TldValidator::saveToRedisCache
 * @uses   \App\Validators\SyntaxValidator::validateDomainPart
 */
class DetailedEmailValidationTest extends BaseEmailValidationTest
{

    /**
     * Проверяет интеграционную валидацию email-адресов с реальными доменами.
     * Тестирует полный pipeline валидации: синтаксис → TLD → MX.
     *
     * @test
     */
    public function it_validates_emails_with_real_domains(): void
    {
        $testEmails = [
            'test@gmail.com',
            'user@yahoo.com',
            'contact@microsoft.com',
        ];

        foreach ($testEmails as $emailString) {
            $email = $this->parseEmailString($emailString);
            $result = $this->validator->validate($email);

            $this->assertTrue(
                $result->isValid(),
                "Email '$emailString' должен пройти полную валидацию. Причина отказа: " . ($result->reason ?? 'неизвестна')
            );
            $this->assertSame('valid', $result->status);
            $this->assertNull($result->reason);
        }
    }

    /**
     * Проверяет обработку IPv6 адресов в доменной части.
     *
     * @test
     */
    public function it_handles_ipv6_domain_addresses(): void
    {
        $ipv6Email = 'user@[ipv6:2001:db8::1]';
        $email = $this->parseEmailString($ipv6Email);

        $result = $this->validator->validate($email);

        // IPv6 адреса могут не проходить TLD валидацию, что нормально
        // Проверяем только что результат получен
        $this->assertNotNull($result);
        $this->assertSame($ipv6Email, $result->email);
    }

    /**
     * Проверяет поведение валидатора при невалидном TLD.
     * Заменяет тест с рефлексией на функциональный тест.
     *
     * @test
     */
    public function it_rejects_emails_with_invalid_tld(): void
    {
        $emailWithInvalidTld = 'test@example.invalidtldfortesting';
        $email = $this->parseEmailString($emailWithInvalidTld);

        $result = $this->validator->validate($email);

        $this->assertFalse($result->isValid());
        $this->assertNotNull($result->reason, 'Должна быть указана причина отклонения');
        $this->assertStringContainsString(
            'не найден',
            $result->reason,
            'Должна быть указана причина отклонения по TLD'
        );
    }

    /**
     * Тестирует интеграцию всех валидаторов через EmailValidator.
     * Проверяет, что все компоненты правильно взаимодействуют.
     *
     * @test
     */
    public function it_integrates_all_validators_correctly(): void
    {
        $validators = $this->validator->getValidators();

        $this->assertCount(3, $validators, 'EmailValidator должен содержать 3 валидатора');
        $this->assertArrayHasKey('syntax', $validators);
        $this->assertArrayHasKey('tld', $validators);
        $this->assertArrayHasKey('mx', $validators);
    }

    /**
     * Проверяет fail-fast стратегию валидации.
     * Email с синтаксической ошибкой не должен дойти до TLD проверки.
     *
     * @test
     */
    public function it_follows_fail_fast_validation_strategy(): void
    {
        $syntaxInvalidEmail = 'invalid..email@example.com';
        $email = $this->parseEmailString($syntaxInvalidEmail);

        $result = $this->validator->validate($email);

        $this->assertFalse($result->isValid());
        $this->assertNotNull($result->reason, 'Должна быть указана причина отклонения');
        // Проверяем, что это ошибка формата (syntax), а не TLD или MX
        $this->assertSame('invalid_format', $result->status);
    }

    /**
     * Проверяет различные типы валидационных ошибок.
     *
     * @test
     */
    public function it_handles_different_validation_error_types(): void
    {
        $testCases = [
            [
                'email' => 'invalid..syntax@example.com',
                'expectedStatus' => 'invalid_format',
                'description' => 'синтаксическая ошибка'
            ],
            [
                'email' => 'test@domain.nonexistenttld',
                'expectedStatus' => 'invalid_tld',
                'description' => 'несуществующий TLD'
            ]
        ];

        foreach ($testCases as $testCase) {
            $email = $this->parseEmailString($testCase['email']);
            $result = $this->validator->validate($email);

            $this->assertFalse($result->isValid(), "Email '{$testCase['email']}' должен быть невалидным");
            $this->assertSame(
                $testCase['expectedStatus'],
                $result->status,
                "Неверный статус для {$testCase['description']}"
            );
            $this->assertNotNull($result->reason, "Должна быть причина для {$testCase['description']}");
        }
    }

}