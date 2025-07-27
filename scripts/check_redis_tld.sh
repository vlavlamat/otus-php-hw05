#!/bin/bash

# ВАЖНО: Этот скрипт должен запускаться из хост-системы, а НЕ из контейнера.
# Скрипт использует команды docker exec для взаимодействия с контейнерами
# redis-cluster-redis-node1-1 и php-fpm1-hw05.
#
# Запуск: ./check_redis_tld.sh из корневой директории проекта
#
# Перед запуском убедитесь, что:
# 1. Docker-контейнеры запущены
# 2. У вас есть права на выполнение этого скрипта (chmod +x check_redis_tld.sh)

echo "=== Проверка TLD кэша в Redis Cluster ==="
echo "Время: $(date)"
echo ""

# Проверка доступности Redis
echo "--- Проверка доступности Redis ---"
if docker exec -it redis-cluster-redis-node1-1 redis-cli -c ping > /dev/null 2>&1; then
    echo "✅ Redis доступен"
else
    echo "❌ Redis недоступен"
    exit 1
fi

# Проверка наличия ключей TLD
echo ""
echo "--- Проверка наличия TLD ключей ---"
KEYS=$(docker exec -it redis-cluster-redis-node1-1 redis-cli -c KEYS "tld_cache:*" | wc -l)
if [ "$KEYS" -eq 2 ]; then
    echo "✅ Найдены оба ключа TLD кэша"

    # Проверка TTL
    echo ""
    echo "--- TTL информация ---"
    TTL_LIST=$(docker exec -it redis-cluster-redis-node1-1 redis-cli -c TTL tld_cache:tlds_list)
    TTL_META=$(docker exec -it redis-cluster-redis-node1-1 redis-cli -c TTL tld_cache:tlds_metadata)

    echo "TTL tlds_list: $TTL_LIST секунд"
    echo "TTL tlds_metadata: $TTL_META секунд"

    # Проверка размера
    echo ""
    echo "--- Размер данных ---"
    SIZE_LIST=$(docker exec -it redis-cluster-redis-node1-1 redis-cli -c STRLEN tld_cache:tlds_list)
    SIZE_META=$(docker exec -it redis-cluster-redis-node1-1 redis-cli -c STRLEN tld_cache:tlds_metadata)

    echo "Размер tlds_list: $SIZE_LIST байт"
    echo "Размер tlds_metadata: $SIZE_META байт"

else
    echo "❌ TLD кэш не найден или неполный"
    echo "Найдено ключей: $KEYS (ожидается 2)"

    echo ""
    echo "--- Создание кэша ---"
    echo "Запуск TLD валидатора для создания кэша..."
    docker exec -it php-fpm1-hw05 php scripts/test_tld_cache.php
fi

echo ""
echo "=== Проверка завершена ==="