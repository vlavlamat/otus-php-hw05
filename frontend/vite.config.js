// Импортируем функцию defineConfig для типизированной настройки Vite
import {defineConfig} from 'vite'
// Импортируем плагин Vue для поддержки Vue SFC (single file components)
import vue from '@vitejs/plugin-vue'

// Экспортируем конфигурацию Vite
export default defineConfig({
    // Подключаем плагин Vue
    plugins: [vue()],

    // Настройки для dev-сервера
    server: {
        host: true, // позволяет принимать подключения извне (не только localhost)
        port: 5173, // порт, на котором запускается dev-сервер

        // Прокси для перенаправления API-запросов на backend
        proxy: {
            '/api': {
                target: 'http://nginx-proxy', // адрес nginx (например, docker-сервис с именем nginx-proxy)
                changeOrigin: true, // меняет origin заголовок, чтобы соответствовать целевому серверу
            }
        }
    },

    // Настройки сборки
    build: {
        outDir: 'dist' // папка, куда будут помещены собранные файлы при команде vite build
    }
})
