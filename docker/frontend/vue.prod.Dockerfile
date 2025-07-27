# docker/frontend/vue.prod.Dockerfile

### Stage 1 - сборка фронтенда
# Берем базовый образ Node.js 22 и создаем временный контейнер для сборки "builder"
FROM node:22-alpine AS builder

# Устанавливаем рабочую директорию внутри контейнера
WORKDIR /app

# Копируем только package.json и package-lock.json для кэширования зависимостей
COPY frontend/package*.json ./

# Устанавливаем npm зависимости
RUN npm install

# Копируем весь исходный код фронтенда
COPY frontend/ ./

# Запускаем сборку продакшн версии (создается папка dist)
RUN npm run build


### Stage 2 - веб-сервер для фронтенда
# Берем образ Nginx на Alpine
FROM nginx:stable-alpine

# Удаляем дефолтный конфиг nginx
RUN rm /etc/nginx/conf.d/default.conf

# Добавляем свой конфиг nginx для фронтенда
COPY ./nginx/frontend/default.conf /etc/nginx/conf.d/default.conf

# КЛЮЧЕВАЯ СТРОКА: копируем собранные файлы из временного контейнера "builder" в frontend-nginx
COPY --from=builder /app/dist /usr/share/nginx/html

EXPOSE 80

# Запускаем nginx в foreground режиме
CMD ["nginx", "-g", "daemon off;"]

# "nginx" - это исполняемая программа (веб-сервер Nginx)
# "-g" - флаг для передачи глобальной директивы
# "daemon off;" - сама директива, которая говорит Nginx работать в foreground режиме (не становиться демоном)
