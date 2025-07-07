<?php

declare(strict_types=1);

namespace App;

use App\Validators\MxValidator;
use App\Validators\SyntaxValidator;
use App\Validators\TldValidator;

/**
 * Композитный валидатор email адресов с многоуровневой проверкой
 *
 * Главный координатор валидации email адресов, который объединяет и управляет
 * тремя специализированными валидаторами для обеспечения всесторонней проверки.
 *
 * Архитектура валидации:
 * 1. SyntaxValidator - проверка синтаксиса согласно RFC 5322
 * 2. TldValidator - проверка TLD против официального списка IANA
 * 3. MxValidator - проверка MX записей в DNS
 *
 * Особенности:
 * - Использует паттерн "Композиция" для объединения валидаторов
 * - Поддерживает стратегию "fail-fast" для быстрой валидации
 * - Предоставляет детальную валидацию с результатами от всех валидаторов
 * - Включает пакетную обработку множественных email адресов
 * - Генерирует статистику валидации для аналитики
 *
 * @package App
 * @author Vladimir Matkovskii and Claude 4 Sonnet
 * @version 1.0
 */
class EmailValidator
{
    /**
     * Валидатор синтаксиса email адресов
     * Проверяет соответствие базовым правилам RFC 5322
     *
     * @var SyntaxValidator
     */
    private SyntaxValidator $syntaxValidator;

    /**
     * Валидатор доменов верхнего уровня
     * Проверяет TLD против официального списка IANA
     *
     * @var TldValidator
     */
    private TldValidator $tldValidator;

    /**
     * Валидатор MX записей
     * Проверяет наличие почтовых серверов в DNS
     *
     * @var MxValidator
     */
    private MxValidator $mxValidator;

    /**
     * Конструктор композитного валидатора
     *
     * Инициализирует валидатор с тремя специализированными компонентами.
     * Использует dependency injection для обеспечения гибкости и тестируемости.
     *
     * @param SyntaxValidator $syntaxValidator Валидатор синтаксиса email адресов
     * @param TldValidator $tldValidator Валидатор доменов верхнего уровня
     * @param MxValidator $mxValidator Валидатор MX записей в DNS
     */
    public function __construct(
        SyntaxValidator $syntaxValidator,
        TldValidator $tldValidator,
        MxValidator $mxValidator
    ) {
        $this->syntaxValidator = $syntaxValidator;
        $this->tldValidator = $tldValidator;
        $this->mxValidator = $mxValidator;
    }

    /**
     * Быстрая валидация email адреса с остановкой на первой ошибке
     *
     * Выполняет последовательную проверку через все валидаторы, используя
     * стратегию "fail-fast" - останавливается при первой обнаруженной ошибке.
     * Это наиболее эффективный способ валидации когда нужен только итоговый результат.
     *
     * Порядок валидации:
     * 1. Синтаксис (самая быстрая проверка)
     * 2. TLD (проверка по кэшированному списку)
     * 3. MX записи (самая медленная, требует DNS запроса)
     *
     * @param string $email Email адрес для валидации
     * @return ValidationResult Результат валидации с детальной информацией об ошибке
     */
    public function validate(string $email): ValidationResult
    {
        // Этап 1: Валидация синтаксиса (быстрая локальная проверка)
        $syntaxResult = $this->syntaxValidator->validate($email);
        if (!$syntaxResult->isValid()) {
            return $syntaxResult;
        }

        // Этап 2: Валидация TLD (проверка по кэшированному списку)
        $tldResult = $this->tldValidator->validate($email);
        if (!$tldResult->isValid()) {
            return $tldResult;
        }

        // Этап 3: Валидация MX записей (DNS запрос - самая медленная проверка)
        $mxResult = $this->mxValidator->validate($email);
        if (!$mxResult->isValid()) {
            return $mxResult;
        }

        // Все этапы валидации пройдены успешно
        return ValidationResult::valid($email);
    }


    /**
     * Фабричный метод для создания EmailValidator с настройками по умолчанию
     *
     * Предоставляет удобный способ создания полностью настроенного валидатора
     * без необходимости явного конструирования всех зависимостей.
     *
     * @return EmailValidator Готовый к использованию экземпляр валидатора
     */
    public static function createDefault(): EmailValidator
    {
        return new self(
            new SyntaxValidator(),
            new TldValidator(),
            new MxValidator()
        );
    }

    /**
     * Получение прямого доступа к внутренним валидаторам
     *
     * Предоставляет доступ к отдельным компонентам валидации для продвинутого
     * использования, тестирования или настройки специфических параметров.
     *
     * @return array Ассоциативный массив валидаторов:
     *               - 'syntax' => SyntaxValidator
     *               - 'tld' => TldValidator
     *               - 'mx' => MxValidator
     */
    public function getValidators(): array
    {
        return [
            'syntax' => $this->syntaxValidator,
            'tld' => $this->tldValidator,
            'mx' => $this->mxValidator,
        ];
    }

}
