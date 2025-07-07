<?php

declare(strict_types=1);

namespace App;

/**
 * Валидатор HTTP запросов для извлечения email адресов из текста
 *
 * Этот класс отвечает за валидацию и обработку входящих HTTP запросов,
 * содержащих текстовые данные для извлечения email адресов. Он служит
 * первым защитным барьером в цепочке обработки данных и обеспечивает
 * корректность входных данных перед их передачей в бизнес-логику.
 *
 * Основные возможности:
 * - Валидация структуры входящих JSON запросов
 * - Проверка типов данных и обязательных полей
 * - Ограничение размера текста для предотвращения DoS атак
 * - Поддержка многобайтовых символов (UTF-8)
 * - Детальные сообщения об ошибках для фронтенда
 * - Безопасное извлечение данных из валидированных структур
 *
 * Архитектурные принципы:
 * - Следует принципу "fail-fast" - останавливается при первой критической ошибке
 * - Использует defensive programming для защиты от некорректных данных
 * - Предоставляет структурированные результаты валидации
 * - Поддерживает интернационализацию через UTF-8
 * - Разделяет логику валидации и извлечения данных
 *
 * Сценарии использования:
 * - Валидация форм с текстовыми полями на фронтенде
 * - Обработка массовых загрузок текстовых данных
 * - Проверка данных из внешних API
 * - Предварительная обработка данных из CSV/TXT файлов
 * - Защита от злоумышленных запросов большого размера
 *
 * @package App
 * @author Vladimir Matkovskii and Claude 4 Sonnet
 * @version 1.0
 */
class ValidationRequest
{
    /**
     * Максимальное количество символов в тексте для обработки
     *
     * Это ограничение защищает сервер от:
     * - DoS атак через отправку очень больших текстов
     * - Превышения лимитов памяти при обработке
     * - Таймаутов выполнения скриптов
     * - Перегрузки регулярных выражений при парсинге
     *
     * Размер 15000 символов выбран как разумный компромисс между:
     * - Возможностью обработки больших списков email (≈300-500 адресов)
     * - Защитой от злоупотреблений и перегрузки системы
     * - Типичными размерами текстовых полей в веб-формах
     */
    private const MAX_TEXT_LENGTH = 15000;

    /**
     * Минимальная длина текста для обработки
     * Предотвращает обработку пустых или бессмысленных запросов
     */
    private const MIN_TEXT_LENGTH = 1;

    /**
     * Список обязательных полей в запросе
     * Определяет минимальную структуру валидного запроса
     */
    private const REQUIRED_FIELDS = ['text'];

    /**
     * Валидирует структуру и содержимое входящего HTTP запроса
     *
     * Выполняет многоуровневую проверку входящих данных:
     * 1. Проверка наличия обязательных полей
     * 2. Валидация типов данных
     * 3. Проверка длины текста и UTF-8 кодировки
     * 4. Дополнительные проверки безопасности
     *
     * Метод использует структурированный подход к валидации, собирая
     * все ошибки перед возвратом результата, что позволяет пользователю
     * получить полную картину проблем в запросе.
     *
     * Входные данные ожидаются в формате:
     * [
     *   "text" => "user@example.com, test@domain.org\nmore emails..."
     * ]
     *
     * Выходные данные в формате:
     * [
     *   "valid" => true|false,
     *   "errors" => ["список ошибок"],
     *   "data" => ["исходные данные"]
     * ]
     *
     * @param array $data Ассоциативный массив с данными запроса
     * @return array Результат валидации с флагом успеха, ошибками и данными
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Этап 1: Проверка наличия обязательных полей
        if (!isset($data['text'])) {
            $errors[] = 'Поле "text" обязательно для заполнения';
        }

        // Этап 2: Валидация типов данных
        if (isset($data['text']) && !is_string($data['text'])) {
            $errors[] = 'Поле "text" должно быть строкой';
        }

        // Этап 3: Проверка длины и содержимого текста
        if (isset($data['text']) && is_string($data['text'])) {
            $textLength = mb_strlen($data['text'], 'UTF-8');

            // Проверяем максимальную длину
            if ($textLength > self::MAX_TEXT_LENGTH) {
                $errors[] = sprintf(
                    'Текст слишком длинный. Максимум %d символов, получено %d',
                    self::MAX_TEXT_LENGTH,
                    $textLength
                );
            }

            // Проверяем минимальную длину после trim
            if ($textLength === 0 || empty(trim($data['text']))) {
                $errors[] = 'Поле "text" не может быть пустым';
            }
        }

        // Этап 4: Дополнительная валидация на потенциально опасные данные
        if (isset($data['text']) && is_string($data['text'])) {
            // Проверяем валидность UTF-8 кодировки
            if (!mb_check_encoding($data['text'], 'UTF-8')) {
                $errors[] = 'Текст содержит некорректные UTF-8 символы';
            }

            // Проверяем на подозрительные паттерны (опционально)
            if ($this->containsSuspiciousPatterns($data['text'])) {
                $errors[] = 'Текст содержит подозрительные символы или паттерны';
            }
        }

        // Возвращаем структурированный результат валидации
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $data
        ];
    }

    /**
     * Безопасно извлекает текст из валидированных данных
     *
     * Этот метод предназначен для использования после успешной валидации
     * и гарантирует безопасное извлечение текстовых данных. Использует
     * null coalescing operator для предотвращения ошибок при отсутствии данных.
     *
     * Применение:
     * - Извлечение данных после успешной валидации
     * - Безопасный доступ к вложенным структурам данных
     * - Предотвращение ошибок при работе с опциональными полями
     *
     * @param array $validatedData Данные, прошедшие валидацию методом validate()
     * @return string Текст для дальнейшей обработки, пустая строка если данных нет
     */
    public function getText(array $validatedData): string
    {
        return $validatedData['data']['text'] ?? '';
    }

    /**
     * Проверяет наличие в тексте подозрительных паттернов
     *
     * Дополнительная проверка безопасности, которая ищет в тексте
     * потенциально опасные символы или паттерны, которые могут указывать
     * на попытки инъекций или других атак.
     *
     * Проверяемые паттерны:
     * - Чрезмерное количество специальных символов
     * - Подозрительные HTML/JavaScript конструкции
     * - Потенциальные SQL инъекции
     * - Слишком длинные строки без пробелов
     *
     * @param string $text Текст для проверки
     * @return bool true, если найдены подозрительные паттерны
     */
    private function containsSuspiciousPatterns(string $text): bool
    {
        // Проверяем на чрезмерное количество специальных символов
        $specialCharsCount = preg_match_all('/[<>{}[\]\\\\]/', $text);
        if ($specialCharsCount > 50) {
            return true;
        }

        // Проверяем на подозрительные HTML/JavaScript паттерны
        $suspiciousPatterns = [
            '/<script[^>]*>/i',
            '/<\/script>/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        // Проверяем на потенциальные SQL инъекции
        $sqlPatterns = [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        // Проверяем на слишком длинные строки без пробелов (возможные атаки)
        $words = preg_split('/\s+/', $text);
        foreach ($words as $word) {
            if (strlen($word) > 1000) {
                return true;
            }
        }

        return false;
    }

    /**
     * Валидирует массив данных запроса с дополнительными проверками
     *
     * Расширенная версия метода validate() с дополнительными проверками
     * для специфических сценариев использования.
     *
     * @param array $data Данные запроса
     * @param array $options Опции валидации
     * @return array Результат валидации
     */
    public function validateWithOptions(array $data, array $options = []): array
    {
        // Получаем базовый результат валидации
        $result = $this->validate($data);

        // Применяем дополнительные опции валидации
        if (isset($options['strict_mode']) && $options['strict_mode']) {
            $result = $this->applyStrictValidation($result, $data);
        }

        if (isset($options['custom_max_length']) && is_int($options['custom_max_length'])) {
            $result = $this->validateCustomLength($result, $data, $options['custom_max_length']);
        }

        return $result;
    }

    /**
     * Применяет строгую валидацию с дополнительными проверками
     *
     * @param array $result Текущий результат валидации
     * @param array $data Исходные данные
     * @return array Обновленный результат валидации
     */
    private function applyStrictValidation(array $result, array $data): array
    {
        if (!isset($data['text']) || !is_string($data['text'])) {
            return $result;
        }

        // Проверяем на наличие хотя бы одного символа @ (потенциальный email)
        if (!str_contains($data['text'], '@')) {
            $result['errors'][] = 'Текст не содержит символов "@", возможно отсутствуют email адреса';
            $result['valid'] = false;
        }

        return $result;
    }

    /**
     * Валидирует длину текста с пользовательским лимитом
     *
     * @param array $result Текущий результат валидации
     * @param array $data Исходные данные
     * @param int $customMaxLength Пользовательский лимит длины
     * @return array Обновленный результат валидации
     */
    private function validateCustomLength(array $result, array $data, int $customMaxLength): array
    {
        if (!isset($data['text']) || !is_string($data['text'])) {
            return $result;
        }

        $textLength = mb_strlen($data['text'], 'UTF-8');
        if ($textLength > $customMaxLength) {
            $result['errors'][] = sprintf(
                'Текст превышает установленный лимит. Максимум %d символов, получено %d',
                $customMaxLength,
                $textLength
            );
            $result['valid'] = false;
        }

        return $result;
    }

    /**
     * Получает информацию о размере и структуре текста
     *
     * Полезный метод для аналитики и отладки, который возвращает
     * детальную информацию о содержимом текста.
     *
     * @param string $text Текст для анализа
     * @return array Информация о тексте
     */
    public function getTextInfo(string $text): array
    {
        return [
            'length' => mb_strlen($text, 'UTF-8'),
            'byte_length' => strlen($text),
            'lines_count' => substr_count($text, "\n") + 1,
            'words_count' => str_word_count($text),
            'at_symbols_count' => substr_count($text, '@'),
            'encoding' => mb_detect_encoding($text, 'UTF-8, ASCII, ISO-8859-1'),
            'has_suspicious_patterns' => $this->containsSuspiciousPatterns($text),
        ];
    }
}