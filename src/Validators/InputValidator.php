<?php

declare(strict_types=1);

namespace App\Validators;

use InvalidArgumentException;

/**
 * Валидатор входных данных для проверки ограничений
 *
 * @package App\Validators
 */
class InputValidator
{
    /** Максимальная длина входного текста в символах */
    private const MAX_TEXT_LENGTH = 1000;

    /** Максимальное количество email адресов в одном запросе */
    private const MAX_EMAIL_COUNT = 30;

    /** Минимальная длина входного текста в символах */
    private const MIN_TEXT_LENGTH = 1;

    /** Минимальное количество email адресов в запросе */
    private const MIN_EMAIL_COUNT = 1;

    /**
     * Валидирует длину входного текста
     *
     * @param string $text Входной текст для проверки
     * @throws InvalidArgumentException Если текст не соответствует ограничениям
     */
    public function validateTextLength(string $text): void
    {
        $normalizedText = trim($text);
        $textLength = strlen($normalizedText);

        if ($textLength < self::MIN_TEXT_LENGTH) {
            throw new InvalidArgumentException(
                "Входной текст не может быть пустым. Минимальная длина: " . self::MIN_TEXT_LENGTH . " символ"
            );
        }

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
     * @param array $items Массив для проверки
     * @param string $itemType Тип элементов для сообщений об ошибках
     * @throws InvalidArgumentException Если количество элементов не соответствует ограничениям
     */
    public function validateArraySize(array $items, string $itemType = "элементов"): void
    {
        $itemCount = count($items);

        if ($itemCount < self::MIN_EMAIL_COUNT) {
            throw new InvalidArgumentException(
                "Массив $itemType не может быть пустым. Минимальное количество: " . self::MIN_EMAIL_COUNT
            );
        }

        if ($itemCount > self::MAX_EMAIL_COUNT) {
            throw new InvalidArgumentException(
                "Слишком много $itemType. Максимальное количество: " . self::MAX_EMAIL_COUNT . ", " .
                "получено: $itemCount"
            );
        }
    }
}
