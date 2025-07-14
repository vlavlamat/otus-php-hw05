<?php
declare(strict_types=1);

namespace App\Redis\Health;

use Exception;
use RedisCluster;
use RedisClusterException;

/**
 * Класс RedisHealthChecker
 *
 * Отвечает за проверку состояния Redis Cluster и предоставление
 * информации о доступности кластера для мониторинга и отображения
 * статуса соединения на фронтенде.
 */
class RedisHealthChecker
{
    /**
     * Экземпляр подключения к Redis Cluster
     *
     * @var RedisCluster
     */
    private RedisCluster $cluster;

    /**
     * Конфигурация Redis Cluster
     *
     * @var array
     */
    private array $config;

    /**
     * Конструктор класса
     *
     * Инициализирует подключение к Redis Cluster, используя конфигурацию из config/redis.php.
     * При создании экземпляра класса автоматически устанавливается соединение
     * с кластером Redis.
     *
     * @param array|null $config Опциональная конфигурация Redis (для тестирования)
     * @throws RedisClusterException если невозможно подключиться к Redis Cluster
     */
    public function __construct(?array $config = null)
    {
        // Загружаем конфигурацию Redis (используем переданную или загружаем из файла)
        $this->config = $config ?? require __DIR__ . '/../../../config/redis.php';

        try {
            // Инициализируем подключение к Redis Cluster используя узлы из конфигурации
            $this->cluster = new RedisCluster(
                null,
                $this->config['cluster']['nodes'],
                $this->config['cluster']['timeout'] ?? 5,
                $this->config['cluster']['read_timeout'] ?? 5
            );

            // Проверяем соединение, выполнив простую команду
            // Используем первый узел из конфигурации для проверки
            $this->cluster->ping($this->config['cluster']['nodes'][0]);
        } catch (Exception $e) {
            // Если произошла любая ошибка при подключении или проверке соединения,
            // выбрасываем RedisClusterException
            throw new RedisClusterException(
                'Failed to connect to Redis Cluster: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Проверяет состояние всех узлов Redis кластера
     *
     * Метод отправляет ping-запрос к каждому узлу Redis Cluster
     * и возвращает массив со статусом каждого узла.
     *
     * @return array Ассоциативный массив, где ключи - имена узлов, значения - их статус
     */
    public function getClusterStatus(): array
    {
        // Получаем список узлов из конфигурации
        $nodes = $this->config['cluster']['nodes'];

        $status = []; // Инициализируем массив для хранения статусов

        // Проверяем каждый узел отдельно
        foreach ($nodes as $node) {
            try {
                // Отправляем ping конкретному узлу
                $pingResult = $this->cluster->ping($node);

                // Проверяем ответ: '+PONG' или 1 означает успешное соединение
                $status[$node] = ($pingResult == 1 || $pingResult === '+PONG' || $pingResult === 'PONG')
                    ? 'connected'    // Узел доступен
                    : 'disconnected'; // Узел недоступен или вернул неожиданный ответ
            } catch (Exception $e) {
                // Если произошла ошибка, сохраняем информацию о ней
                $status[$node] = 'error: ' . $e->getMessage();
            }
        }

        return $status; // Возвращаем статусы всех узлов
    }

    /**
     * Проверяет общее состояние Redis Cluster
     *
     * Метод определяет, считается ли кластер работоспособным.
     * Кластер считается доступным, если количество работающих узлов
     * соответствует настройкам кворума из конфигурации.
     * Это обеспечивает отказоустойчивость кластера при выходе из строя
     * меньшинства узлов.
     *
     * @return bool true, если кластер работоспособен, false в противном случае
     */
    public function isConnected(): bool
    {
        // Получаем статус всех узлов
        $status = $this->getClusterStatus();
        $connectedCount = 0;

        // Подсчитываем количество доступных узлов
        foreach ($status as $nodeStatus) {
            if ($nodeStatus === 'connected') {
                $connectedCount++;
            }
        }

        // Получаем требуемый кворум из конфигурации
        $requiredQuorum = $this->config['cluster']['quorum'];

        // Кластер доступен, если количество подключенных узлов >= кворума
        return $connectedCount >= $requiredQuorum;
    }

    /**
     * Возвращает требуемый кворум для кластера
     *
     * @return int Минимальное количество узлов для работоспособности кластера
     */
    public function getRequiredQuorum(): int
    {
        return $this->config['cluster']['quorum'];
    }
}
