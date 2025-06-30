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
     * - Локальная часть: буквы, цифры, точки, дефисы, подчеркивания
     * - Домен: буквы, цифры, дефисы, точки
     * - TLD: от 2 до 6 букв
     */
    private const EMAIL_PATTERN = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/';

    /**
     * Извлекает все email-адреса из переданного текста
     *
     * @param string $text Текст для поиска email-адресов
     * @return array Массив найденных уникальных email-адресов
     */
    public function extractEmails(string $text): array
    {
        $emails = [];

        // Используем регулярное выражение для поиска всех email-адресов
        if (preg_match_all(self::EMAIL_PATTERN, $text, $matches)) {
            $emails = $matches[0];
        }

        // Убираем дубликаты и приводим к нижнему регистру
        $emails = array_map('strtolower', $emails);
        $emails = array_unique($emails);

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

    /**
     * Подсчитывает количество email-адресов в тексте
     *
     * @param string $text Текст для подсчета
     * @return int Количество найденных email-адресов
     */
    public function countEmails(string $text): int
    {
        return count($this->extractEmails($text));
    }
}