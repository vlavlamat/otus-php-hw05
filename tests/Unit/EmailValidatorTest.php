<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ValidationResult;
use App\Validators\EmailValidator;
use App\Validators\SyntaxValidator;
use App\Validators\TldValidator;
use App\Validators\MxValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit тесты для EmailValidator класса
 *
 * Покрывает основную логику, fail-fast стратегию, фабричный метод и геттеры
 *
 * @covers    \App\Validators\EmailValidator
 * @covers    \App\Models\ValidationResult
 * @covers    \App\Validators\SyntaxValidator
 * @covers    \App\Validators\TldValidator
 * @covers    \App\Validators\MxValidator
 * @covers    \App\Redis\Adapters\RedisCacheAdapter
 */
class EmailValidatorTest extends TestCase
{
    private SyntaxValidator|MockObject $syntaxValidatorMock;
    private TldValidator|MockObject $tldValidatorMock;
    private MxValidator|MockObject $mxValidatorMock;
    private EmailValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syntaxValidatorMock = $this->createMock(SyntaxValidator::class);
        $this->tldValidatorMock = $this->createMock(TldValidator::class);
        $this->mxValidatorMock = $this->createMock(MxValidator::class);

        $this->validator = new EmailValidator(
            $this->syntaxValidatorMock,
            $this->tldValidatorMock,
            $this->mxValidatorMock
        );
    }

    /**
     * Тест основной логики validate() - успешная валидация
     */
    public function testValidateReturnsValidWhenAllValidatorsPass(): void
    {
        $email = ['local' => 'test', 'domain' => 'example.com', 'full' => 'test@example.com'];

        // Настраиваем моки для успешной валидации
        $this->syntaxValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('test@example.com')
            ->willReturn(ValidationResult::valid('test@example.com'));

        $this->tldValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('test@example.com')
            ->willReturn(ValidationResult::valid('test@example.com'));

        $this->mxValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('test@example.com')
            ->willReturn(ValidationResult::valid('test@example.com'));

        $result = $this->validator->validate($email);

        $this->assertTrue($result->isValid());
        $this->assertSame('test@example.com', $result->email);
        $this->assertSame('valid', $result->status);
        $this->assertNull($result->reason);
    }

    /**
     * Тест fail-fast логики - остановка на синтаксической ошибке
     */
    public function testValidateReturnsInvalidFormatWhenSyntaxFails(): void
    {
        $email = ['local' => 'invalid..email', 'domain' => 'example.com', 'full' => 'invalid..email@example.com'];

        $syntaxResult = ValidationResult::invalidFormat('invalid..email@example.com', 'Двойные точки недопустимы');

        $this->syntaxValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('invalid..email@example.com')
            ->willReturn($syntaxResult);

        // TLD и MX валидаторы не должны вызываться при ошибке синтаксиса
        $this->tldValidatorMock->expects($this->never())->method('validate');
        $this->mxValidatorMock->expects($this->never())->method('validate');

        $result = $this->validator->validate($email);

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
        $this->assertSame('Двойные точки недопустимы', $result->reason);
    }

    /**
     * Тест fail-fast логики - остановка на ошибке TLD
     */
    public function testValidateReturnsInvalidTldWhenSyntaxPassesTldFails(): void
    {
        $email = ['local' => 'test', 'domain' => 'example.invalidtld', 'full' => 'test@example.invalidtld'];

        $this->syntaxValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('test@example.invalidtld')
            ->willReturn(ValidationResult::valid('test@example.invalidtld'));

        $tldResult = ValidationResult::invalidTld('test@example.invalidtld', 'TLD не найден в списке IANA');
        $this->tldValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('test@example.invalidtld')
            ->willReturn($tldResult);

        // MX валидатор не должен вызываться при ошибке TLD
        $this->mxValidatorMock->expects($this->never())->method('validate');

        $result = $this->validator->validate($email);

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_tld', $result->status);
        $this->assertSame('TLD не найден в списке IANA', $result->reason);
    }

    /**
     * Тест fail-fast логики - остановка на ошибке MX
     */
    public function testValidateReturnsInvalidMxWhenSyntaxTldPassMxFails(): void
    {
        $email = ['local' => 'test', 'domain' => 'example.com', 'full' => 'test@example.com'];

        $this->syntaxValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('test@example.com')
            ->willReturn(ValidationResult::valid('test@example.com'));

        $this->tldValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('test@example.com')
            ->willReturn(ValidationResult::valid('test@example.com'));

        $mxResult = ValidationResult::invalidMx('test@example.com', 'MX запись не найдена');
        $this->mxValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('test@example.com')
            ->willReturn($mxResult);

        $result = $this->validator->validate($email);

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_mx', $result->status);
        $this->assertSame('MX запись не найдена', $result->reason);
    }

    /**
     * Тест порядка выполнения валидаторов
     */
    public function testValidationOrderIsSyntaxTldMx(): void
    {
        $email = ['local' => 'test', 'domain' => 'example.com', 'full' => 'test@example.com'];

        $callOrder = [];

        $this->syntaxValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'syntax';
                return ValidationResult::valid('test@example.com');
            });

        $this->tldValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'tld';
                return ValidationResult::valid('test@example.com');
            });

        $this->mxValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'mx';
                return ValidationResult::valid('test@example.com');
            });

        $this->validator->validate($email);

        $this->assertSame(['syntax', 'tld', 'mx'], $callOrder);
    }

    /**
     * Тест фабричного метода createDefault()
     */
    public function testCreateDefaultReturnsConfiguredValidator(): void
    {
        $validator = EmailValidator::createDefault();

        $validators = $validator->getValidators();
        $this->assertCount(3, $validators);
        $this->assertInstanceOf(SyntaxValidator::class, $validators['syntax']);
        $this->assertInstanceOf(TldValidator::class, $validators['tld']);
        $this->assertInstanceOf(MxValidator::class, $validators['mx']);
    }

    /**
     * Тест геттера getValidators()
     */
    public function testGetValidatorsReturnsAllThreeValidators(): void
    {
        $validators = $this->validator->getValidators();

        $this->assertCount(3, $validators);
        $this->assertArrayHasKey('syntax', $validators);
        $this->assertArrayHasKey('tld', $validators);
        $this->assertArrayHasKey('mx', $validators);

        $this->assertSame($this->syntaxValidatorMock, $validators['syntax']);
        $this->assertSame($this->tldValidatorMock, $validators['tld']);
        $this->assertSame($this->mxValidatorMock, $validators['mx']);
    }

    /**
     * Тест конструктора с DI
     */
    public function testConstructorInjectsValidators(): void
    {
        $syntaxValidator = $this->createMock(SyntaxValidator::class);
        $tldValidator = $this->createMock(TldValidator::class);
        $mxValidator = $this->createMock(MxValidator::class);

        $validator = new EmailValidator($syntaxValidator, $tldValidator, $mxValidator);
        $validators = $validator->getValidators();

        $this->assertSame($syntaxValidator, $validators['syntax']);
        $this->assertSame($tldValidator, $validators['tld']);
        $this->assertSame($mxValidator, $validators['mx']);
    }

    /**
     * Тест обработки пустого массива email
     */
    public function testValidateHandlesEmptyEmailArray(): void
    {
        $email = [];

        $this->syntaxValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('')
            ->willReturn(ValidationResult::invalidFormat('', 'Email адрес не может быть пустым'));

        $result = $this->validator->validate($email);

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
    }

    /**
     * Тест обработки массива без ключа 'full'
     */
    public function testValidateHandlesEmailArrayWithoutFullKey(): void
    {
        $email = ['local' => 'test', 'domain' => 'example.com'];

        $this->syntaxValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('')
            ->willReturn(ValidationResult::invalidFormat('', 'Email адрес не может быть пустым'));

        $result = $this->validator->validate($email);

        $this->assertFalse($result->isValid());
        $this->assertSame('invalid_format', $result->status);
    }
}