# Указываем, что эти цели (targets) не являются файлами,
# а просто именованными действиями, которые всегда должны выполняться.
.PHONY: prod-up prod-down prod-logs ps prod-update prod-clean

# ────────────────────────────────
# Переменные
# ────────────────────────────────

# Docker Hub username
REGISTRY_USER = vlavlamat

# ────────────────────────────────
# Production окружение (сервер)
# ────────────────────────────────

prod-up:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

prod-down:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml down

prod-logs:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml logs -f --tail=100

# ────────────────────────────────
# Обновление образов и контейнеров
# ────────────────────────────────

prod-update:
	docker pull $(REGISTRY_USER)/php-fpm-hw05:prod
	docker pull $(REGISTRY_USER)/nginx-backend-hw05:prod
	docker pull $(REGISTRY_USER)/nginx-proxy-hw05:prod
	docker pull $(REGISTRY_USER)/vue-frontend-hw05:prod
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --remove-orphans

# ────────────────────────────────
# Очистка старых dangling-образов
# ────────────────────────────────

prod-clean:
	docker image prune -f

# ────────────────────────────────
# Список запущенных контейнеров и их статуса
# ────────────────────────────────

ps:
	docker compose ps