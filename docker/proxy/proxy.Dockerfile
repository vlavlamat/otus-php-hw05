FROM nginx:stable-alpine

# Удаляем дефолтный конфиг
RUN rm /etc/nginx/conf.d/default.conf

# Добавляем свой конфиг
COPY ./nginx/proxy/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]

# "nginx" - это исполняемая программа (веб-сервер Nginx)
# "-g" - флаг для передачи глобальной директивы
# "daemon off;" - сама директива, которая говорит Nginx работать в foreground режиме (не становиться демоном)
