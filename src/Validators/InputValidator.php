<?php

declare(strict_types=1);

namespace App\Validators;

use InvalidArgumentException;

/**
 * Валидатор входных данных для проверки ограничений
 *
 * Выполняет проверку различных ограничений на входные данные перед их обработкой.
 * Предназначен для валидации ограничений на размер, количество элементов и другие
 * бизнес-правила приложения.
 *
 * Основные функции:
 * - Проверка длины текста
 * - Проверка количества элементов в массиве
 * - Проверка типов данных
 * - Валидация бизнес-ограничений
 *
 * Все методы выбрасывают исключения при нарушении ограничений для обеспечения
 * fail-fast подхода и четкой обработки ошибок.
 *
 * @package App\Validators
 * @author Vladimir Matkovskii and Claude 4 Sonnet
 * @version 1.0
 */
class InputValidator
{
    /**
     * Максимальная длина входного текста в символах
     *
     * Это ограничение защищает от DoS атак через передачу очень больших
     * строк и обеспечивает разумное использование памяти сервера.
     * 20000 символов достаточно для обработки большинства реальных случаев.
     */
    private const MAX_TEXT_LENGTH = 20000;

    /**
     * Максимальное количество email адресов в одном запросе
     *
     * Ограничение на количество email адресов предотвращает перегрузку
     * сервера при обработке больших пакетов данных. 1000 адресов
     * является разумным балансом между функциональностью и производительностью.
     */
    private const MAX_EMAIL_COUNT = 1000;

    /**
     * Минимальная длина входного текста в символах
     *
     * Минимальное ограничение для предотвращения пустых запросов
     * и обеспечения валидности входных данных.
     */
    private const MIN_TEXT_LENGTH = 1;

    /**
     * Минимальное количество email адресов в запросе
     *
     * Минимальное ограничение для обеспечения смысла запроса.
     * Пустые массивы не должны обрабатываться.
     */
    private const MIN_EMAIL_COUNT = 1;

    /**
     * Максимальная длина одного email адреса
     *
     * Согласно RFC 5321, максимальная длина email адреса составляет 320 символов:
     * - 64 символа для локальной части
     * - 1 символ для @
     * - 255 символов для доменной части
     */
    private const MAX_EMAIL_LENGTH = 320;

    /**
     * Валидирует длину входного текста
     *
     * Проверяет, что длина текста находится в допустимых пределах.
     * Это критически важная проверка для предотвращения DoS атак
     * и обеспечения стабильности сервиса.
     *
     * Выполняемые проверки:
     * - Текст не может быть пустым после trim
     * - Длина не может превышать максимальный лимит
     * - Длина не может быть меньше минимального значения
     *
     * @param string $text Входной текст для проверки
     * @return void
     * @throws InvalidArgumentException Если текст не соответствует ограничениям
     *
     * @example
     * $validator = new InputValidator();
     * $validator->validateTextLength("user@example.com, user2@test.com");
     * // Проходит без исключений
     *
     * $validator->validateTextLength("");
     * // Выбросит InvalidArgumentException
     */
    public function validateTextLength(string $text): void
    {
        // Нормализуем текст для проверки
        $normalizedText = trim($text);
        $textLength = strlen($normalizedText);

        // Проверяем минимальную длину
        if ($textLength < self::MIN_TEXT_LENGTH) {
            throw new InvalidArgumentException(
                "Входной текст не может быть пустым. Минимальная длина: " . self::MIN_TEXT_LENGTH . " символ"
            );
        }

        // Проверяем максимальную длину
        if ($textLength > self::MAX_TEXT_LENGTH) {
            throw new InvalidArgumentException(
                "Входной текст слишком длинный. Максимальная длина: " . self::MAX_TEXT_LENGTH . " символов, " .
                "получено: $textLength символов"
            );
        }
    }

    /**
     * Валидирует количество элементов в массиве
     *
     * Проверяет, что количество элементов в массиве находится в допустимых пределах.
     * Это важно для предотвращения перегрузки сервера при обработке больших
     * массивов данных.
     *
     * Выполняемые проверки:
     * - Массив не может быть пустым
     * - Количество элементов не может превышать максимальный лимит
     * - Количество элементов не может быть меньше минимального значения
     *
     * @param array $items Массив для проверки
     * @param string $itemType Тип элементов для сообщений об ошибках (например, "email адресов")
     * @return void
     * @throws InvalidArgumentException Если количество элементов не соответствует ограничениям
     *
     * @example
     * $validator = new InputValidator();
     * $emails = ["user@example.com", "user2@test.com"];
     * $validator->validateArraySize($emails, "email адресов");
     * // Проходит без исключений
     *
     * $validator->validateArraySize([], "email адресов");
     * // Выбросит InvalidArgumentException
     */
    public function validateArraySize(array $items, string $itemType = "элементов"): void
    {
        $itemCount = count($items);

        // Проверяем минимальное количество элементов
        if ($itemCount < self::MIN_EMAIL_COUNT) {
            throw new InvalidArgumentException(
                "Массив $itemType не может быть пустым. Минимальное количество: " . self::MIN_EMAIL_COUNT
            );
        }

        // Проверяем максимальное количество элементов
        if ($itemCount > self::MAX_EMAIL_COUNT) {
            throw new InvalidArgumentException(
                "Слишком много $itemType. Максимальное количество: " . self::MAX_EMAIL_COUNT . ", " .
                "получено: $itemCount"
            );
        }
    }

    /**
     * Валидирует отдельный email адрес на соответствие базовым ограничениям
     *
     * Проверяет базовые ограничения для одного email адреса перед его
     * детальной валидацией. Это быстрая проверка для фильтрации очевидно
     * некорректных данных.
     *
     * Выполняемые проверки:
     * - Email не может быть пустым после trim
     * - Длина email не может превышать максимально допустимую
     * - Email должен содержать основные символы (@ и точку)
     *
     * @param string $email Email адрес для проверки
     * @return void
     * @throws InvalidArgumentException Если email не соответствует базовым ограничениям
     *
     * @example
     * $validator = new InputValidator();
     * $validator->validateSingleEmail("user@example.com");
     * // Проходит без исключений
     *
     * $validator->validateSingleEmail("");
     * // Выбросит InvalidArgumentException
     */
    public function validateSingleEmail(string $email): void
    {
        // Нормализуем email для проверки
        $normalizedEmail = trim($email);

        // Проверяем на пустоту
        if (empty($normalizedEmail)) {
            throw new InvalidArgumentException("Email адрес не может быть пустым");
        }

        // Проверяем длину
        $emailLength = strlen($normalizedEmail);
        if ($emailLength > self::MAX_EMAIL_LENGTH) {
            throw new InvalidArgumentException(
                "Email адрес слишком длинный. Максимальная длина: " . self::MAX_EMAIL_LENGTH . " символов, " .
                "получено: $emailLength символов"
            );
        }

        // Базовая проверка на наличие @ символа
        if (!str_contains($normalizedEmail, '@')) {
            throw new InvalidArgumentException("Email адрес должен содержать символ @");
        }

        // Базовая проверка на наличие точки в доменной части
        if (!str_contains($normalizedEmail, '.')) {
            throw new InvalidArgumentException("Email адрес должен содержать точку в доменной части");
        }
    }

    /**
     * Валидирует массив email адресов на соответствие базовым ограничениям
     *
     * Комбинированная проверка, которая валидирует как размер массива,
     * так и каждый email адрес в отдельности. Это обеспечивает комплексную
     * валидацию входных данных.
     *
     * Выполняемые проверки:
     * - Размер массива в допустимых пределах
     * - Каждый email соответствует базовым ограничениям
     * - Отсутствие очевидно некорректных данных
     *
     * @param array $emails Массив email адресов для проверки
     * @return void
     * @throws InvalidArgumentException Если массив или отдельные email не соответствуют ограничениям
     *
     * @example
     * $validator = new InputValidator();
     * $emails = ["user@example.com", "user2@test.com"];
     * $validator->validateEmailArray($emails);
     * // Проходит без исключений
     *
     * $emails = ["", "invalid"];
     * $validator->validateEmailArray($emails);
     * // Выбросит InvalidArgumentException для первого пустого email
     */
    public function validateEmailArray(array $emails): void
    {
        // Сначала проверяем размер массива
        $this->validateArraySize($emails, "email адресов");

        // Затем проверяем каждый email отдельно
        foreach ($emails as $index => $email) {
            try {
                // Приводим к строке для безопасности
                $emailString = (string)$email;
                $this->validateSingleEmail($emailString);
            } catch (InvalidArgumentException $e) {
                // Добавляем информацию об индексе для отладки
                throw new InvalidArgumentException(
                    "Ошибка в email адресе №" . ($index + 1) . ": " . $e->getMessage()
                );
            }
        }
    }

    /**
     * Валидирует тип данных переменной
     *
     * Проверяет, что переменная имеет ожидаемый тип данных.
     * Полезно для валидации входных данных API, где тип может быть неопределенным.
     *
     * @param mixed $value Значение для проверки
     * @param string $expectedType Ожидаемый тип данных
     * @param string $variableName Имя переменной для сообщений об ошибках
     * @return void
     * @throws InvalidArgumentException Если тип данных не соответствует ожидаемому
     *
     * @example
     * $validator = new InputValidator();
     * $validator->validateDataType("test", "string", "email");
     * // Проходит без исключений
     *
     * $validator->validateDataType(123, "string", "email");
     * // Выбросит InvalidArgumentException
     */
    public function validateDataType(mixed $value, string $expectedType, string $variableName): void
    {
        $actualType = gettype($value);

        if ($actualType !== $expectedType) {
            throw new InvalidArgumentException(
                "Неверный тип данных для '$variableName'. Ожидается: $expectedType, получено: $actualType"
            );
        }
    }

    /**
     * Получает текущие ограничения валидатора
     *
     * Возвращает массив с текущими ограничениями для использования
     * в документации API, тестировании и отладке.
     *
     * @return array Ассоциативный массив с ограничениями
     *
     * @example
     * $validator = new InputValidator();
     * $limits = $validator->getLimits();
     * // Результат: ["max_text_length" => 20000, "max_email_count" => 1000, ...]
     */
    public function getLimits(): array
    {
        return [
            'max_text_length' => self::MAX_TEXT_LENGTH,
            'max_email_count' => self::MAX_EMAIL_COUNT,
            'min_text_length' => self::MIN_TEXT_LENGTH,
            'min_email_count' => self::MIN_EMAIL_COUNT,
            'max_email_length' => self::MAX_EMAIL_LENGTH,
        ];
    }

    /**
     * Проверяет, является ли размер данных допустимым
     *
     * Быстрая проверка без выбрасывания исключений. Возвращает boolean результат.
     * Полезно для предварительной проверки данных без обработки исключений.
     *
     * @param string $text Текст для проверки
     * @return bool True, если размер допустим
     *
     * @example
     * $validator = new InputValidator();
     * $isValid = $validator->isValidTextSize("test@example.com");
     * // Результат: true
     *
     * $isValid = $validator->isValidTextSize(str_repeat("a", 25000));
     * // Результат: false
     */
    public function isValidTextSize(string $text): bool
    {
        try {
            $this->validateTextLength($text);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Проверяет, является ли размер массива допустимым
     *
     * Быстрая проверка без выбрасывания исключений. Возвращает boolean результат.
     * Полезно для предварительной проверки данных без обработки исключений.
     *
     * @param array $items Массив для проверки
     * @return bool True, если размер допустим
     *
     * @example
     * $validator = new InputValidator();
     * $isValid = $validator->isValidArraySize(["test@example.com"]);
     * // Результат: true
     *
     * $isValid = $validator->isValidArraySize([]);
     * // Результат: false
     */
    public function isValidArraySize(array $items): bool
    {
        try {
            $this->validateArraySize($items);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Возвращает информацию о производительности валидатора
     *
     * Полезно для мониторинга и оптимизации производительности.
     * Показывает настройки, которые влияют на скорость обработки.
     *
     * @return array Информация о производительности
     *
     * @example
     * $validator = new InputValidator();
     * $performance = $validator->getPerformanceInfo();
     * // Результат: информация о лимитах и их влиянии на производительность
     */
    public function getPerformanceInfo(): array
    {
        return [
            'limits' => $this->getLimits(),
            'validation_strategy' => 'fail-fast',
            'complexity' => 'O(n) для массивов, O(1) для отдельных элементов',
            'memory_usage' => 'Минимальное - только проверка размеров',
            'performance_notes' => [
                'Текстовая валидация: O(1) - только проверка strlen',
                'Валидация массива: O(n) - проверка каждого элемента',
                'Валидация email: O(1) - только базовые проверки',
            ]
        ];
    }
}