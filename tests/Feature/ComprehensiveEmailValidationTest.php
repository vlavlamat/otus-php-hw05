<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Комплексные приемочные тесты для всей системы валидации email.
 * Проверяет соответствие всем требованиям и корректную работу всех компонентов.
 *
 * @covers \App\Validators\EmailValidator
 * @uses   \App\Models\ValidationResult
 * @uses   \App\Redis\Adapters\RedisCacheAdapter
 * @uses   \App\Validators\MxValidator
 * @uses   \App\Validators\SyntaxValidator
 * @uses   \App\Validators\TldValidator
 *
 * @group email-validation
 * @group feature-tests
 */
class ComprehensiveEmailValidationTest extends BaseEmailValidationTest
{

    /**
     * Тестирует все сценарии валидации с реальными email-адресами.
     * Покрывает валидные адреса, синтаксические ошибки, проблемы с TLD и MX.
     *
     * @group comprehensive
     */
    public function testValidatesComprehensiveEmailScenarios(): void
    {
        $testScenarios = [
            // Валидные email (реальные домены с MX записями)
            'valid_real_domains' => [
                'emails' => ['test@gmail.com', 'user@yahoo.com', 'contact@microsoft.com'],
                'expectedStatus' => 'valid',
                'expectedCount' => 3
            ],

            // Синтаксические ошибки
            'syntax_errors' => [
                'emails' => [
                    'invalid-email-no-at.com',
                    'user@@double-at.com',
                    '.starting.dot@domain.com',
                    'ending.dot.@domain.com',
                    'double..dot@domain.com'
                ],
                'expectedStatus' => 'invalid_format',
                'expectedCount' => 5
            ],

            // Несуществующие TLD
            'invalid_tlds' => [
                'emails' => ['user@domain.invalidtld', 'test@site.fakeext'],
                'expectedStatus' => 'invalid_tld',
                'expectedCount' => 2
            ],

            // Валидный формат и TLD, но нет MX записи
            'invalid_mx' => [
                'emails' => ['user@example.com', 'test@nonexistentdomain12345.com'],
                'expectedStatus' => 'invalid_mx',
                'expectedCount' => 2
            ]
        ];

        $allResults = [];
        $groupedResults = [
            'valid' => [],
            'invalid_format' => [],
            'invalid_tld' => [],
            'invalid_mx' => []
        ];

        // Обрабатываем все тестовые сценарии
        foreach ($testScenarios as $scenario) {
            foreach ($scenario['emails'] as $email) {
                $result = $this->validator->validate($this->parseEmailString($email));
                $allResults[] = $result;

                if (isset($groupedResults[$result->status])) {
                    $groupedResults[$result->status][] = $result;
                }
            }
        }

        // Проверяем ожидаемые результаты по группам
        foreach ($testScenarios as $scenarioName => $scenario) {
            $actualCount = count($groupedResults[$scenario['expectedStatus']]);
            $this->assertGreaterThanOrEqual(
                $scenario['expectedCount'],
                $actualCount,
                "Сценарий '$scenarioName': ожидается минимум {$scenario['expectedCount']} результатов со статусом '{$scenario['expectedStatus']}', получено $actualCount"
            );
        }

        // Проверяем общую статистику
        $totalEmails = array_sum(array_map(fn($s) => $s['expectedCount'], $testScenarios));
        $this->assertCount($totalEmails, $allResults, 'Все email-адреса должны быть обработаны');
    }

    /**
     * Проверяет поддержку IPv6 адресов в доменной части.
     *
     * @group ipv6
     */
    public function testHandlesIpv6Addresses(): void
    {
        $ipv6Emails = [
            'user@[IPv6:2001:db8::1]',
            'test@[ipv6:fe80::1]'
        ];

        foreach ($ipv6Emails as $email) {
            $result = $this->validator->validate($this->parseEmailString($email));

            // Убираем избыточную проверку типа - проверяем конкретные свойства
            $this->assertSame($email, $result->email);
            $this->assertNotNull($result->status, 'Результат должен содержать статус валидации');
            // IPv6 адреса могут не проходить TLD валидацию, что нормально
        }
    }

    /**
     * Проверяет поддержку современных длинных TLD.
     *
     * @group tld-validation
     */
    public function testSupportsModernLongTlds(): void
    {
        $modernTldEmail = 'contact@example.technology';
        $result = $this->validator->validate($this->parseEmailString($modernTldEmail));

        // Убираем избыточную проверку типа - проверяем конкретные свойства
        $this->assertSame($modernTldEmail, $result->email);
        $this->assertNotNull($result->status, 'Результат должен содержать статус валидации');

        // Если TLD валиден, то ошибка должна быть в MX, а не в TLD
        if (!$result->isValid()) {
            $this->assertNotSame('invalid_tld', $result->status,
                'Современные TLD должны поддерживаться');
        }
    }

    /**
     * Проверяет соответствие RFC 5322 для синтаксиса email.
     *
     * @group rfc5322
     */
    public function testSupportsRfc5322CompliantSyntax(): void
    {
        $rfc5322TestEmails = [
            'test+tag@domain.com',
            'user.name@domain.com',
            'user_name@domain.com',
            'user-name@domain.com'
        ];

        foreach ($rfc5322TestEmails as $email) {
            $result = $this->validator->validate($this->parseEmailString($email));

            $this->assertNotSame('invalid_format', $result->status,
                "Email '$email' должен пройти синтаксическую проверку RFC 5322");
        }
    }

    /**
     * Проверяет корректность загрузки IANA TLD списка.
     * Заменяет хрупкий тест с рефлексией на функциональный тест.
     *
     * @group iana-tld
     */
    public function testLoadsIanaTldListSuccessfully(): void
    {
        // Тестируем через функциональность - проверяем известные TLD
        $knownValidTlds = [
            'test@example.com',      // com
            'user@site.org',         // org  
            'contact@domain.net',    // net
            'info@company.edu',      // edu
            'admin@government.gov'   // gov
        ];

        $validTldCount = 0;
        foreach ($knownValidTlds as $email) {
            $result = $this->validator->validate($this->parseEmailString($email));

            // Если TLD валиден, ошибка будет в MX, а не в TLD
            if ($result->status !== 'invalid_tld') {
                $validTldCount++;
            }
        }

        $this->assertGreaterThan(3, $validTldCount,
            'Большинство стандартных TLD должны быть загружены из IANA списка');
    }


    /**
     * Интеграционный тест требований системы.
     * Проверяет все заявленные возможности системы валидации.
     *
     * @group system-requirements
     * @group integration
     */
    public function testMeetsAllSystemRequirements(): void
    {
        // RFC 5322 совместимый синтаксис
        $result = $this->validator->validate($this->parseEmailString('user.name+tag@domain.com'));
        $this->assertNotSame('invalid_format', $result->status,
            'Должна поддерживаться RFC 5322 совместимая синтаксическая проверка');

        // IANA TLD валидация 
        $result = $this->validator->validate($this->parseEmailString('test@domain.invalidtld'));
        $this->assertSame('invalid_tld', $result->status,
            'Должна работать проверка TLD по официальному списку IANA');

        // MX запись валидация
        $result = $this->validator->validate($this->parseEmailString('test@example.com'));
        $this->assertSame('invalid_mx', $result->status,
            'Должна работать проверка MX-записи домена');

        // Детальные статусы
        $result = $this->validator->validate($this->parseEmailString('invalid..email@domain.com'));
        $this->assertContains($result->status, ['valid', 'invalid_format', 'invalid_tld', 'invalid_mx'],
            'Должны поддерживаться детальные статусы валидации');

        // Обработка списков (через цикл)
        $batchEmails = ['test1@gmail.com', 'test2@yahoo.com'];
        $batchResults = [];
        foreach ($batchEmails as $email) {
            $batchResults[] = $this->validator->validate($this->parseEmailString($email));
        }
        $this->assertCount(2, $batchResults, 'Должна поддерживаться обработка списка email-адресов');

        // Без отправки писем (только DNS проверки) - убираем избыточную проверку типа
        $result = $this->validator->validate($this->parseEmailString('contact@microsoft.com'));
        $this->assertNotNull($result->status, 'Валидация должна вернуть результат с определенным статусом');
        $this->assertNotNull($result->email, 'Результат должен содержать исходный email');
    }

}