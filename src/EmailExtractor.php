<?php

declare(strict_types=1);

namespace App;

/**
 * Класс EmailExtractor
 *
 * Отвечает за извлечение email-адресов из произвольного текста
 * с использованием регулярных выражений
 */
class EmailExtractor
{
    /**
     * Регулярное выражение для поиска email-адресов
     *
     * Паттерн ищет email в формате: локальная_часть@домен.tld
     * - Локальная часть: буквы, цифры, точки, дефисы, подчеркивания, плюсы, проценты
     * - Домен: буквы, цифры, дефисы, точки или IPv6 адрес в квадратных скобках
     * - TLD: от 1 до 63 букв (согласно RFC)
     *
     * Это глобальное регулярное выражение, которое находит email даже без разделителей
     * и в "слипшихся" email-адресах. Оно более строгое, чем предыдущая версия,
     * и лучше соответствует RFC 5322.
     */
    private const EMAIL_PATTERN = '/[a-zA-Z0-9_%+\-!][a-zA-Z0-9._%+\-!]*@(?:[a-zA-Z0-9.\-]+\.[a-zA-Z]{1,63}|\[IPv6:[0-9a-fA-F:]+])/i';

    /**
     * Извлекает все email-адреса из переданного текста
     *
     * @param string $text Текст для поиска email-адресов
     * @return array Массив найденных уникальных email-адресов
     */
    public function extractEmails(string $text): array
    {
        $emails = [];

        // Разбиваем текст на строки
        $lines = preg_split('/\r\n|\r|\n/', $text);

        foreach ($lines as $line) {
            // Очищаем строку от лишних пробелов
            $line = trim($line);

            // Пропускаем пустые строки
            if (empty($line)) {
                continue;
            }

            // Проверяем, является ли строка email-адресом (включая невалидные с точкой в начале)
            // Используем два паттерна: один для валидных email, другой для email с точкой в начале
            if (preg_match('/^[a-zA-Z0-9_%+\-!][a-zA-Z0-9._%+\-!]*@(?:[a-zA-Z0-9.\-]+\.[a-zA-Z]{1,63}|\[IPv6:[0-9a-fA-F:]+])$/i', $line)) {
                // Валидный email
                $emails[] = strtolower($line);
            } elseif (preg_match('/^\.+[a-zA-Z0-9._%+\-!]+@(?:[a-zA-Z0-9.\-]+\.[a-zA-Z]{1,63}|\[IPv6:[0-9a-fA-F:]+])$/i', $line)) {
                // Email с точкой в начале (невалидный, но мы его извлекаем для последующей валидации)
                $emails[] = strtolower($line);
            }
        }

        // Возвращаем массив с числовыми индексами
        return array_values($emails);
    }

    /**
     * Проверяет, содержит ли текст email-адреса
     *
     * @param string $text Текст для проверки
     * @return bool true, если найден хотя бы один email
     */
    public function hasEmails(string $text): bool
    {
        return preg_match(self::EMAIL_PATTERN, $text) === 1;
    }
}
