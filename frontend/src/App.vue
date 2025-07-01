<template>
  <div class="container">
    <h1>EmailПроверка+</h1>
    <div class="service-description">Комплексная валидация и верификация email адресов</div>

    <!-- Поле ввода текста с email адресами -->
    <div class="input-block">
      <div class="output-title">Вставьте текст с email адресами:</div>
      <textarea 
        v-model="textInput"
        :maxlength="15000"
        placeholder="Вставьте текст с email адресами (до 15,000 символов)"
        class="text-input-field"
      >
      </textarea>
      <div class="characters-count" :class="{ 'warn': textInput.length > 14000 }">
        {{ textInput.length }}/15000 символов
      </div>
    </div>

    <!-- Кнопки управления -->
    <div class="buttons-container">
      <button class="clear-button" @click="clearTextInput">Очистить список</button>
      <button class="submit-button" @click="validateEmail">Проверить email адреса</button>
    </div>

    <!-- Только краткая статистика о проверке, если были результаты -->
    <div v-if="result" class="stats-container">
      <div class="stats-text" :class="answerClass">{{ result }}</div>
    </div>
  </div>

  <!-- Индикатор статуса Redis Cluster в отдельном контейнере -->
  <div class="redis-status-container">
    <div class="redis-status">
      Redis Cluster: <span :class="redisStatusClass">{{ redisStatusText }}</span>
    </div>
  </div>
</template>

<script setup>
/**
 * @file App.vue
 * @description Компонент приложения для валидации email адресов
 */

// Импортируем функции Vue
import {ref, computed, onMounted, onUnmounted} from 'vue'
// Импортируем axios для отправки HTTP-запросов
import axios from 'axios'

/**
 * Константы для настройки интервалов проверки статуса Redis
 */
const REDIS_STATUS_CHECK_DELAY = 2000
const REDIS_STATUS_CHECK_INTERVAL = 30000

/**
 * Состояние приложения
 */
// Переменная для хранения введённого текста с email адресами
const textInput = ref('')
// Переменная для хранения текста результата (краткой статистики)
const result = ref('')
// Переменная для хранения статуса Redis Cluster
const redisStatus = ref('Loading...')
// Флаг для отслеживания загрузки статуса Redis
const isRedisStatusLoading = ref(true)
// Переменная для хранения идентификатора интервала
let statusInterval = null
// Переменная для хранения оригинального текста перед проверкой
const originalText = ref('')

/**
 * Получает статус Redis Cluster с сервера
 * @async
 * @returns {Promise<void>} Промис без возвращаемого значения
 */
const fetchRedisStatus = async () => {
  try {
    const response = await axios.get('/api/status') // Запрос к backend
    redisStatus.value = response.data.redis_cluster  // Получаем поле redis_cluster
  } catch (error) {
    // 🔍 Детальная обработка разных типов ошибок
    if (error.code === 'NETWORK_ERROR' || !error.response) {
      redisStatus.value = 'network_error'
    } else if (error.response?.status >= 500) {
      redisStatus.value = 'server_error'
    } else if (error.response?.status === 404) {
      redisStatus.value = 'api_not_found'
    } else if (error.response?.status >= 400) {
      redisStatus.value = 'client_error'
    } else {
      redisStatus.value = 'unknown_error'
    }

    // 📝 Логируем для разработчика
    console.error('Redis status error:', {
      message: error.message,
      status: error.response?.status,
      code: error.code,
      url: error.config?.url
    })
  } finally {
    // Устанавливаем флаг загрузки в false после получения статуса
    isRedisStatusLoading.value = false
  }
}

/**
 * Обработчики жизненного цикла компонента
 */
onMounted(() => {
  setTimeout(fetchRedisStatus, REDIS_STATUS_CHECK_DELAY)
  statusInterval = setInterval(fetchRedisStatus, REDIS_STATUS_CHECK_INTERVAL)
})

onUnmounted(() => {
  if (statusInterval) {
    clearInterval(statusInterval)
  }
})

/**
 * Обрабатывает ошибку API
 * @param {Error} error - Объект ошибки от axios
 * @returns {string} Сообщение об ошибке для отображения пользователю
 */
const handleApiError = (error) => {
  if (!error.response) {
    return 'Ошибка сети или сервер недоступен'
  }

  const {status, data} = error.response
  if (status === 400) {
    const errorMessage = data.message || ''
    if (errorMessage.includes('Empty input')) {
      return 'Пустой текст! Status: 400 Bad Request.'
    } else if (errorMessage.includes('Input too large')) {
      return 'Превышен лимит в 15,000 символов! Status: 400 Bad Request.'
    } else {
      return 'Некорректные данные! Status: 400 Bad Request.'
    }
  }

  return `Ошибка сервера: ${status}`
}

/**
 * Возвращает иконку и подробное описание для отображения статуса email
 * @param {string} status - Статус email (valid, invalid)
 * @returns {string} Иконка со статусом и детальным пояснением
 */
const getStatusIcon = (status) => {
  const statusMap = {
    'valid': '✅ валидный email',
    'invalid': '❌ невалидный email'
  }
  return statusMap[status] || '❌ невалидный email'
}

/**
 * Очищает поле ввода текста
 */
const clearTextInput = () => {
  textInput.value = '';
  result.value = '';
}

/**
 * Отправляет текст на сервер для валидации email адресов
 * @async
 */
const validateEmail = async () => {
  // Сохраняем оригинальный текст на случай ошибки
  originalText.value = textInput.value;

  // Проверка на превышение лимита символов
  if (textInput.value.length > 15000) {
    result.value = 'Превышен лимит в 15,000 символов!'
    return
  }

  // Проверка на пустой ввод
  if (textInput.value.trim() === '') {
    result.value = 'Введите текст с email адресами!'
    return
  }

  try {
    // Отправляем запрос на сервер
    const response = await axios.post('/api/verify', {
      text: textInput.value  // Отправляем весь текст
    })

    // Обрабатываем ответ
    const emailResults = response.data.data.emails || []

    // Логируем для отладки
    console.log('Email results:', emailResults)

    // Формируем сообщение о результате для краткой статистики
    if (emailResults.length === 0) {
      result.value = 'Email адреса не найдены.'
    } else {
      // Получаем уникальные валидные email-адреса
      const uniqueValidEmails = new Set();
      emailResults.forEach(email => {
        if (email.status === 'valid') {
          uniqueValidEmails.add(email.email.toLowerCase());
        }
      });

      const validCount = uniqueValidEmails.size;
      console.log('Valid count:', validCount)
      console.log('Unique valid emails:', uniqueValidEmails)
      result.value = `Найдено ${validCount} валидных email`

      // Находим все email в исходном тексте и заменяем их на email с результатом проверки
      let resultText = originalText.value;

      // Сортируем результаты по длине email (от самых длинных к самым коротким)
      // чтобы избежать проблем с заменой вложенных email
      const sortedResults = [...emailResults].sort((a, b) => 
        b.email.length - a.email.length
      );

      // Определяем фиксированную позицию для начала второго столбца
      // Это обеспечит выравнивание всех статусов независимо от длины email
      const COLUMN_WIDTH = 50; // Ширина первого столбца в символах

      // Создаем таблицу с двумя колонками
      // Первая колонка - email, вторая - статус
      // Используем массив строк для хранения результатов

      // Создаем массив строк из исходного текста
      const lines = originalText.value.split('\n');

      // Создаем Set для отслеживания уже обработанных email и строк
      const processedEmails = new Set();
      const processedLines = new Set();

      // Обрабатываем каждый email из результатов
      sortedResults.forEach(item => {
        const lowerEmail = item.email.toLowerCase();

        // Если этот email уже был обработан, пропускаем его
        if (processedEmails.has(lowerEmail)) {
          return;
        }

        // Добавляем email в список обработанных
        processedEmails.add(lowerEmail);

        // Ищем строки, содержащие этот email
        for (let i = 0; i < lines.length; i++) {
          const line = lines[i];
          // Используем более гибкий подход для поиска email в тексте
          // Экранируем специальные символы в email для использования в регулярном выражении
          const escapedEmail = item.email.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

          // 🎯 ИСПРАВЛЕНИЕ: Используем точное совпадение для всех email
          // Используем флаг 'i' для регистронезависимого поиска
          const emailRegex = new RegExp(`^${escapedEmail}$`, 'i');

          if (emailRegex.test(line)) {
            // Заменяем строку на форматированную версию
            // Первая колонка - email или текст, вторая колонка - статус
            const firstColumn = item.email;
            const secondColumn = getStatusIcon(item.status);

            // Создаем строку с фиксированной шириной первой колонки
            lines[i] = firstColumn.padEnd(COLUMN_WIDTH) + secondColumn;

            // Отмечаем строку как обработанную
            processedLines.add(i);
          }
        }
      });

      // Обрабатываем строки, которые не содержат обработанных email и не пустые
      for (let i = 0; i < lines.length; i++) {
        if (!processedLines.has(i) && lines[i].trim() !== '') {
          // Проверяем, содержит ли строка уже статус
          if (!lines[i].includes('✅ валидный email') && !lines[i].includes('❌ невалидный email')) {
            // Проверяем, содержит ли строка email-подобный текст
            // Обновленный паттерн, который также учитывает email с точкой в начале
            const emailPattern = /\.?[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{1,63}/;
            const lineText = lines[i].trim();

            // Если строка содержит email-подобный текст, проверяем его через EmailValidator
            if (emailPattern.test(lineText)) {
              // Извлекаем email-подобный текст
              const match = lineText.match(emailPattern);
              if (match) {
                const emailText = match[0];

                // Проверяем, есть ли этот email в результатах валидации
                // Используем только точное совпадение email-адресов
                const validationResult = emailResults.find(result => {
                  // 🎯 ИСПРАВЛЕНИЕ: Только точное совпадение email-адресов
                  return result.email.toLowerCase() === emailText.toLowerCase();
                });

                if (validationResult) {
                  // Если email найден в результатах, используем его статус
                  lines[i] = emailText.padEnd(COLUMN_WIDTH) + getStatusIcon(validationResult.status);
                } else {
                  // Если email не найден в результатах, помечаем как невалидный
                  lines[i] = lineText.padEnd(COLUMN_WIDTH) + '❌ невалидный email';
                }
              } else {
                // Если не удалось извлечь email, помечаем как невалидный
                lines[i] = lineText.padEnd(COLUMN_WIDTH) + '❌ невалидный email';
              }
            } else {
              // Если строка не содержит email-подобный текст, помечаем как невалидный
              lines[i] = lineText.padEnd(COLUMN_WIDTH) + '❌ невалидный email';
            }
          }
        }
      }

      // Объединяем строки обратно в текст
      resultText = lines.join('\n');

      // Заменяем текст в textarea
      textInput.value = resultText;
    }
  } catch (error) {
    // Обрабатываем ошибки
    result.value = handleApiError(error);
    // Восстанавливаем оригинальный текст в случае ошибки
    textInput.value = originalText.value;
  }
}

/**
 * Вычисляемые свойства
 */

/**
 * Определяет CSS-класс для текста ответа в зависимости от результата
 * @returns {string} CSS-класс (correct, incorrect или neutral)
 */
const answerClass = computed(() => {
  if (result.value.includes('валидных')) {
    return 'correct'
  } else if (result.value.startsWith('Превышен лимит') || 
             result.value.startsWith('Введите текст') || 
             result.value.startsWith('Ошибка') ||
             result.value.startsWith('Некорректные')) {
    return 'incorrect'
  } else {
    return 'neutral'
  }
})

/**
 * Определяет CSS-класс для отображения статуса Redis Cluster
 * @returns {string} CSS-класс для разных типов ошибок
 */
const redisStatusClass = computed(() => {
  const statusMap = {
    'Loading...': 'loading',
    'connected': 'correct',
    'disconnected': 'incorrect',
    'network_error': 'network-error',
    'server_error': 'server-error',
    'api_not_found': 'api-error',
    'client_error': 'client-error',
    'unknown_error': 'unknown-error'
  }

  return statusMap[redisStatus.value] || 'incorrect'
})

/**
 * Определяет отображаемый текст для статуса Redis Cluster
 * @returns {string} Пользовательский текст статуса
 */
const redisStatusText = computed(() => {
  const textMap = {
    'Loading...': 'Loading...',
    'connected': 'Connected',
    'disconnected': 'Disconnected',
    'network_error': 'Network Error',
    'server_error': 'Server Error',
    'api_not_found': 'API Not Found',
    'client_error': 'Request Error',
    'unknown_error': 'Unknown Error'
  }

  return textMap[redisStatus.value] || 'Error'
})
</script>

<style scoped>
/* Основной контейнер приложения */
.container {
  max-width: 820px; /* увеличиваем ширину на ~17% для лучшего отображения email */
  margin: 3rem auto; /* отступ сверху/снизу и автоцентрирование */
  padding: 2rem; /* внутренние отступы */
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; /* современный шрифт */
  background-color: #f9f9f9; /* светлый фон */
  border-radius: 12px; /* скруглённые углы */
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); /* тень вокруг блока */
}

/* Заголовок */
h1 {
  font-size: 2.2rem; /* увеличиваем размер шрифта */
  margin-bottom: 0.5rem; /* отступ снизу */
  text-align: center; /* центрирование текста */
  color: #333; /* тёмный цвет текста */
}

.service-description {
  text-align: center;
  color: #666;
  font-size: 1.1rem;
  margin-bottom: 1.5rem;
}

/* Блок с полем ввода */
.input-block {
  margin-bottom: 1.5rem;
}

/* Заголовок внутри блоков */
.output-title {
  font-size: 1.3rem;
  font-weight: bold;
  margin-bottom: 0.5rem;
  text-align: left;
  color: black;
}

/* Поле ввода текста - увеличиваем размер */
.text-input-field {
  width: 100%;
  padding: 1rem; /* увеличиваем padding */
  font-size: 1.1rem;
  border: 2px solid #ddd; /* более заметная граница */
  border-radius: 8px;
  box-sizing: border-box;
  margin-bottom: 0.5rem;
  resize: vertical; /* позволяем изменять размер по вертикали */
  min-height: 250px; /* минимальная высота */
  font-family: monospace; /* моноширинный шрифт для выравнивания */
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
  white-space: pre; /* сохраняем переносы строк */
}

/* Фокус на поле ввода */
.text-input-field:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

/* Счетчик символов */
.characters-count {
  text-align: right;
  font-size: 0.9rem;
  color: #666;
  margin-bottom: 0.5rem;
}

/* Предупреждение при приближении к лимиту */
.characters-count.warn {
  color: #e67e22;
  font-weight: bold;
}

/* Общие стили кнопок */
button {
  padding: 1rem 2rem; /* увеличиваем padding */
  font-size: 1.1rem;
  border: none;
  border-radius: 8px;
  background-color: #007bff; /* синий цвет */
  color: white;
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.1s ease; /* плавное изменение цвета */
  font-weight: 600;
}

/* Стили для заблокированных кнопок */
button:disabled {
  background-color: #aaa;
  cursor: not-allowed;
  transform: none;
}

/* Стили кнопок при наведении */
button:hover:enabled {
  background-color: #0056b3;
  transform: translateY(-1px);
}

/* Стили для кнопки "Очистить список" при наведении */
.clear-button:hover:enabled {
  background-color: #bd2130; /* темно-красный цвет */
}

/* Стили кнопок при нажатии */
button:active:enabled {
  transform: translateY(0);
}

/* Контейнер для кнопок */
.buttons-container {
  display: flex;
  gap: 1rem;
  margin-top: 0.5rem;
}

/* Стили для кнопки "Проверить" */
.submit-button {
  flex: 1;
  background-color: #007bff; /* синий цвет */
}

/* Стили для кнопки "Очистить список" */
.clear-button {
  flex: 1;
  background-color: #dc3545; /* красный цвет */
}

/* Контейнер для краткой статистики */
.stats-container {
  margin-top: 1.5rem;
  text-align: center;
}

/* Текст статистики */
.stats-text {
  font-size: 1.2rem;
  font-weight: bold;
  padding: 0.5rem;
  border-radius: 8px;
  display: inline-block;
}

/* Цвет текста для корректного результата */
.stats-text.correct {
  color: #28a745; /* зелёный */
  background-color: rgba(40, 167, 69, 0.1);
}

/* Цвет текста для некорректного результата */
.stats-text.incorrect {
  color: #dc3545; /* красный */
  background-color: rgba(220, 53, 69, 0.1);
}

/* Цвет текста для нейтрального состояния (по умолчанию) */
.stats-text.neutral {
  color: #6c757d; /* серый */
  background-color: rgba(108, 117, 125, 0.1);
}

/* Контейнер для индикатора статуса Redis Cluster */
.redis-status-container {
  max-width: 600px;
  margin: 1rem auto 0;
  padding: 0.5rem;
  display: flex;
  justify-content: center;
}

/* Стили для индикатора статуса Redis Cluster */
.redis-status {
  text-align: center;
  font-size: 14px;
  font-weight: 600;
}

/* Стили для статуса Redis Cluster */
.redis-status span.correct {
  color: green;
}

.redis-status span.loading {
  color: #ffc107; /* жёлтый для состояния загрузки */
  animation: pulse 1.5s infinite; /* добавляем пульсирующую анимацию */
}

.redis-status span.incorrect {
  color: red;
}

/* 🎨 Стили для разных типов ошибок */
.redis-status span.network-error {
  color: #ff6b35; /* Оранжевый для сетевых ошибок */
}

.redis-status span.server-error {
  color: #dc3545; /* Красный для серверных ошибок */
}

.redis-status span.api-error {
  color: #6f42c1; /* Фиолетовый для API ошибок */
}

.redis-status span.client-error {
  color: #fd7e14; /* Оранжевый для клиентских ошибок */
}

.redis-status span.unknown-error {
  color: #6c757d; /* Серый для неизвестных ошибок */
}

/* Анимация пульсации для загрузки */
@keyframes pulse {
  0% {
    opacity: 0.6;
  }
  50% {
    opacity: 1;
  }
  100% {
    opacity: 0.6;
  }
}

/* Адаптивность для мобильных устройств */
@media (max-width: 768px) {
  .container {
    margin: 1rem;
    padding: 1.5rem;
    max-width: none;
  }

  h1 {
    font-size: 1.8rem;
  }

  .text-input-field {
    font-size: 16px; /* предотвращаем зум на iOS */
  }

  button {
    padding: 0.8rem 1.5rem;
    font-size: 1rem;
  }
}
</style>
