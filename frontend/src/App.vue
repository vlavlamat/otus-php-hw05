
<template>
  <div class="container">
    <h1>EmailПроверка+</h1>
    <div class="service-description">Комплексная валидация и верификация email адресов</div>

    <!-- 
      Блок ввода текста с email адресами
      
      Основная рабочая область приложения, где пользователь может:
      - Вставить текст с email адресами (до 15,000 символов)
      - Видеть счетчик символов с предупреждением при приближении к лимиту
      - Получать визуальную обратную связь о состоянии ввода
    -->
    <div class="input-block">
      <div class="output-title">Вставьте текст с email адресами:</div>
      <textarea
          v-model="textInput"
          :maxlength="15000"
          placeholder="Вставьте текст с email адресами (до 15,000 символов)"
          class="text-input-field"
      >
      </textarea>
      <!-- Динамический счетчик символов с предупреждением -->
      <div class="characters-count" :class="{ 'warn': textInput.length > 14000 }">
        {{ textInput.length }}/15000 символов
      </div>
    </div>

    <!-- 
      Панель управления с кнопками действий
      
      Предоставляет пользователю основные операции:
      - Очистка поля ввода для быстрого сброса
      - Запуск процесса валидации email адресов
    -->
    <div class="buttons-container">
      <button class="clear-button" @click="clearTextInput">Очистить список</button>
      <button class="submit-button" @click="validateEmail">Проверить email адреса</button>
    </div>

    <!-- 
      Область отображения результатов валидации
      
      Показывается только после получения результатов проверки.
      Отображает краткую статистику о количестве валидных email адресов
      с соответствующим цветовым кодированием для быстрого восприятия.
    -->
    <div v-if="result" class="stats-container">
      <div class="stats-text" :class="answerClass">{{ result }}</div>
    </div>
  </div>

  <!-- 
    Индикатор статуса Redis Cluster
    
    Отдельный контейнер для мониторинга состояния backend инфраструктуры.
    Показывает текущий статус подключения к Redis Cluster с автоматическим
    обновлением каждые 30 секунд. Помогает отслеживать доступность сервиса.
  -->
  <div class="redis-status-container">
    <div class="redis-status">
      Redis Cluster: <span :class="redisStatusClass">{{ redisStatusText }}</span>
    </div>
  </div>
</template>

<script setup>
/**
 * Главный компонент приложения EmailПроверка+
 *
 * Этот Vue 3 Composition API компонент обеспечивает:
 * - Интерфейс для массовой валидации email адресов
 * - Мониторинг состояния Redis Cluster в реальном времени
 * - Обработку ошибок и пользовательскую обратную связь
 * - Адаптивный дизайн для различных устройств
 * - Оптимизированную обработку больших объемов текста
 *
 * Архитектура:
 * - Composition API для логической группировки функционала
 * - Реактивные переменные для управления состоянием
 * - Вычисляемые свойства для динамического стилизования
 * - Axios для HTTP коммуникации с backend API
 */

// Импорт основных функций Vue 3 Composition API
import {ref, computed, onMounted, onUnmounted} from 'vue'
// Импорт HTTP клиента для взаимодействия с backend
import axios from 'axios'

/**
 * Конфигурационные константы для мониторинга Redis
 *
 * Эти значения настраивают частоту проверки статуса Redis Cluster
 * для балансировки между актуальностью информации и нагрузкой на сервер.
 */
const REDIS_STATUS_CHECK_DELAY = 2000    // Задержка первой проверки (2 сек)
const REDIS_STATUS_CHECK_INTERVAL = 30000 // Интервал между проверками (30 сек)

/**
 * Реактивные переменные состояния приложения
 *
 * Все переменные используют Vue 3 ref() для обеспечения реактивности.
 * Изменения в этих переменных автоматически обновляют UI компонента.
 */

// Основное содержимое поля ввода с email адресами
const textInput = ref('')

// Текст результата валидации для отображения пользователю
const result = ref('')

// Текущий статус подключения к Redis Cluster
const redisStatus = ref('Loading...')

// Флаг состояния загрузки статуса Redis
const isRedisStatusLoading = ref(true)

// Идентификатор интервала для периодической проверки Redis
let statusInterval = null

// Резервная копия исходного текста для восстановления при ошибках
const originalText = ref('')

/**
 * Функция получения статуса Redis Cluster
 *
 * Выполняет HTTP запрос к backend API для получения текущего состояния
 * Redis Cluster. Обрабатывает различные типы ошибок и предоставляет
 * детальную информацию о проблемах подключения.
 *
 * @async
 * @function
 * @returns {Promise<void>} Промис без возвращаемого значения
 */
const fetchRedisStatus = async () => {
  try {
    // Отправляем GET запрос к API статуса
    const response = await axios.get('/api/status')

    // Извлекаем статус Redis Cluster из ответа
    redisStatus.value = response.data.redis_cluster

  } catch (error) {
    /**
     * Детальная обработка различных типов ошибок
     *
     * Классифицируем ошибки по типам для более точного отображения
     * состояния системы и помощи в диагностике проблем.
     */
    if (error.code === 'NETWORK_ERROR' || !error.response) {
      // Сетевые ошибки или отсутствие ответа от сервера
      redisStatus.value = 'network_error'
    } else if (error.response?.status >= 500) {
      // Серверные ошибки (5xx)
      redisStatus.value = 'server_error'
    } else if (error.response?.status === 404) {
      // API эндпоинт не найден
      redisStatus.value = 'api_not_found'
    } else if (error.response?.status >= 400) {
      // Клиентские ошибки (4xx)
      redisStatus.value = 'client_error'
    } else {
      // Неизвестные типы ошибок
      redisStatus.value = 'unknown_error'
    }

    /**
     * Логирование ошибок для разработчиков
     *
     * Подробная информация об ошибке сохраняется в консоли браузера
     * для упрощения отладки и мониторинга проблем в production.
     */
    console.error('Redis status error:', {
      message: error.message,
      status: error.response?.status,
      code: error.code,
      url: error.config?.url
    })
  } finally {
    // Сбрасываем флаг загрузки независимо от результата
    isRedisStatusLoading.value = false
  }
}

/**
 * Хуки жизненного цикла компонента
 *
 * Управляют инициализацией и очисткой ресурсов при монтировании
 * и размонтировании компонента.
 */

// Инициализация при монтировании компонента
onMounted(() => {
  // Первая проверка статуса с небольшой задержкой
  setTimeout(fetchRedisStatus, REDIS_STATUS_CHECK_DELAY)

  // Установка периодической проверки статуса
  statusInterval = setInterval(fetchRedisStatus, REDIS_STATUS_CHECK_INTERVAL)
})

// Очистка ресурсов при размонтировании компонента
onUnmounted(() => {
  // Очищаем интервал для предотвращения утечек памяти
  if (statusInterval) {
    clearInterval(statusInterval)
  }
})

/**
 * Обработчик ошибок API
 *
 * Централизованная функция для обработки ошибок от backend API.
 * Преобразует технические ошибки в понятные пользователю сообщения.
 *
 * @param {Error} error - Объект ошибки от axios
 * @returns {string} Пользовательское сообщение об ошибке
 */
const handleApiError = (error) => {
  // Проверяем наличие ответа от сервера
  if (!error.response) {
    return 'Ошибка сети или сервер недоступен'
  }

  // Извлекаем статус и данные из ответа
  const {status, data} = error.response

  // Обрабатываем различные типы ошибок валидации
  if (status === 400) {
    const errorMessage = data.message || ''

    // Специфичные сообщения для известных ошибок
    if (errorMessage.includes('Empty input')) {
      return 'Пустой текст! Status: 400 Bad Request.'
    } else if (errorMessage.includes('Input too large')) {
      return 'Превышен лимит в 15,000 символов! Status: 400 Bad Request.'
    } else {
      return 'Некорректные данные! Status: 400 Bad Request.'
    }
  }

  // Общий обработчик для других HTTP ошибок
  return `Ошибка сервера: ${status}`
}

/**
 * Функция форматирования статуса email
 *
 * Преобразует технические статусы валидации в визуальные иконки
 * с понятными пользователю описаниями.
 *
 * @param {string} status - Статус валидации email (valid/invalid)
 * @returns {string} Иконка со статусом и описанием
 */
const getStatusIcon = (status) => {
  const statusMap = {
    'valid': '✅ валидный email',
    'invalid': '❌ невалидный email'
  }
  return statusMap[status] || '❌ невалидный email'
}

/**
 * Функция очистки поля ввода
 *
 * Сбрасывает все пользовательские данные и результаты валидации
 * для начала новой сессии проверки email адресов.
 */
const clearTextInput = () => {
  textInput.value = ''
  result.value = ''
}

/**
 * Основная функция валидации email адресов
 *
 * Выполняет комплексную валидацию введенного текста:
 * - Проверяет лимиты и корректность ввода
 * - Отправляет данные на backend для валидации
 * - Обрабатывает результаты и форматирует их для отображения
 * - Заменяет исходный текст на результаты с цветовым кодированием
 *
 * @async
 * @function
 */
const validateEmail = async () => {
  // Сохраняем исходный текст для возможного восстановления
  originalText.value = textInput.value

  /**
   * Предварительная валидация ввода
   *
   * Проверяем базовые требования к вводу перед отправкой на сервер
   * для экономии сетевых ресурсов и быстрой обратной связи.
   */

  // Проверка лимита символов
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
    /**
     * Отправка запроса на backend
     *
     * Передаем весь введенный текст как единый элемент массива
     * для обработки на сервере с помощью EmailController.
     */
    const response = await axios.post('/api/verify', {
      emails: [textInput.value]
    })

    // Извлекаем результаты валидации из ответа
    const emailResults = response.data.results || []

    // Логируем результаты для отладки
    console.log('Email results:', emailResults)

    /**
     * Обработка результатов валидации
     *
     * Анализируем полученные данные и формируем статистику
     * для отображения пользователю.
     */
    if (emailResults.length === 0) {
      result.value = 'Email адреса не найдены.'
    } else {
      /**
       * Подсчет уникальных валидных email адресов
       *
       * Используем Set для исключения дубликатов и получения
       * точного количества уникальных валидных адресов.
       */
      const uniqueValidEmails = new Set()
      emailResults.forEach(email => {
        if (email.status === 'valid') {
          uniqueValidEmails.add(email.email.toLowerCase())
        }
      })

      const validCount = uniqueValidEmails.size
      console.log('Valid count:', validCount)
      console.log('Unique valid emails:', uniqueValidEmails)

      // Формируем краткую статистику
      result.value = `Найдено ${validCount} валидных email`

      /**
       * Форматирование результатов в тексте
       *
       * Заменяем исходный текст на отформатированную версию
       * с результатами валидации в виде двухколоночной таблицы.
       */
      let resultText = originalText.value

      /**
       * Сортировка результатов по длине
       *
       * Сортируем от самых длинных к самым коротким email
       * для предотвращения проблем с заменой вложенных адресов.
       */
      const sortedResults = [...emailResults].sort((a, b) =>
          b.email.length - a.email.length
      )

      // Фиксированная ширина первой колонки для выравнивания
      const COLUMN_WIDTH = 50

      /**
       * Создание таблицы результатов
       *
       * Преобразуем исходный текст в двухколоночную таблицу:
       * - Первая колонка: email адрес
       * - Вторая колонка: статус валидации с иконкой
       */
      const lines = originalText.value.split('\n')
      const processedEmails = new Set()
      const processedLines = new Set()

      /**
       * Обработка каждого email из результатов валидации
       *
       * Находим соответствующие строки в исходном тексте
       * и заменяем их на отформатированные результаты.
       */
      sortedResults.forEach(item => {
        const lowerEmail = item.email.toLowerCase()

        // Пропускаем уже обработанные email
        if (processedEmails.has(lowerEmail)) {
          return
        }

        processedEmails.add(lowerEmail)

        // Поиск строк с текущим email
        for (let i = 0; i < lines.length; i++) {
          const line = lines[i]

          // Экранируем специальные символы для регулярного выражения
          const escapedEmail = item.email.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

          // Точное совпадение с игнорированием регистра
          const emailRegex = new RegExp(`^${escapedEmail}$`, 'i')

          if (emailRegex.test(line)) {
            // Форматируем строку с фиксированной шириной колонок
            const firstColumn = item.email
            const secondColumn = getStatusIcon(item.status)

            lines[i] = firstColumn.padEnd(COLUMN_WIDTH) + secondColumn
            processedLines.add(i)
          }
        }
      })

      /**
       * Обработка необработанных строк
       *
       * Строки, которые не были обработаны выше, проверяем
       * на наличие email-подобного текста и помечаем соответственно.
       */
      for (let i = 0; i < lines.length; i++) {
        if (!processedLines.has(i) && lines[i].trim() !== '') {
          // Проверяем, не содержит ли строка уже статус
          if (!lines[i].includes('✅ валидный email') && !lines[i].includes('❌ невалидный email')) {
            // Паттерн для поиска email-подобного текста
            const emailPattern = /\.?[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{1,63}/
            const lineText = lines[i].trim()

            if (emailPattern.test(lineText)) {
              // Извлекаем email-подобный текст
              const match = lineText.match(emailPattern)
              if (match) {
                const emailText = match[0]

                // Ищем этот email в результатах валидации
                const validationResult = emailResults.find(result => {
                  return result.email.toLowerCase() === emailText.toLowerCase()
                })

                if (validationResult) {
                  // Используем результат валидации
                  lines[i] = emailText.padEnd(COLUMN_WIDTH) + getStatusIcon(validationResult.status)
                } else {
                  // Помечаем как невалидный, если не найден в результатах
                  lines[i] = lineText.padEnd(COLUMN_WIDTH) + '❌ невалидный email'
                }
              } else {
                lines[i] = lineText.padEnd(COLUMN_WIDTH) + '❌ невалидный email'
              }
            } else {
              // Строка не содержит email-подобный текст
              lines[i] = lineText.padEnd(COLUMN_WIDTH) + '❌ невалидный email'
            }
          }
        }
      }

      // Объединяем строки обратно в текст и обновляем поле ввода
      resultText = lines.join('\n')
      textInput.value = resultText
    }
  } catch (error) {
    /**
     * Обработка ошибок валидации
     *
     * В случае ошибки отображаем соответствующее сообщение
     * и восстанавливаем исходный текст.
     */
    result.value = handleApiError(error)
    textInput.value = originalText.value
  }
}

/**
 * Вычисляемые свойства для динамического стилизования
 *
 * Эти свойства автоматически пересчитываются при изменении
 * зависимых реактивных переменных и используются для
 * динамического применения CSS классов.
 */

/**
 * Определение CSS класса для текста результата
 *
 * Анализирует содержимое результата валидации и возвращает
 * соответствующий CSS класс для цветового кодирования.
 *
 * @returns {string} CSS класс (correct/incorrect/neutral)
 */
const answerClass = computed(() => {
  if (result.value.includes('валидных')) {
    return 'correct'  // Зеленый для успешных результатов
  } else if (result.value.startsWith('Превышен лимит') ||
      result.value.startsWith('Введите текст') ||
      result.value.startsWith('Ошибка') ||
      result.value.startsWith('Некорректные')) {
    return 'incorrect'  // Красный для ошибок
  } else {
    return 'neutral'  // Серый для нейтральных состояний
  }
})

/**
 * Определение CSS класса для статуса Redis
 *
 * Сопоставляет различные состояния Redis Cluster с
 * соответствующими CSS классами для визуальной индикации.
 *
 * @returns {string} CSS класс для статуса Redis
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
 * Определение отображаемого текста для статуса Redis
 *
 * Преобразует внутренние коды состояний в понятные
 * пользователю текстовые описания.
 *
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
/**
 * Стили компонента EmailПроверка+
 * 
 * Современный, адаптивный дизайн с акцентом на:
 * - Удобочитаемость и доступность
 * - Визуальную обратную связь
 * - Адаптивность для различных устройств
 * - Семантическое цветовое кодирование статусов
 */

/**
 * Основной контейнер приложения
 * 
 * Центрированный блок с тенью и скругленными углами
 * для создания современного card-based интерфейса.
 */
.container {
  max-width: 820px; /* Увеличенная ширина для лучшего отображения email */
  margin: 3rem auto; /* Автоцентрирование с отступами */
  padding: 2rem; /* Внутренние отступы */
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
  background-color: #f9f9f9; /* Светлый фон */
  border-radius: 12px; /* Скругленные углы */
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); /* Мягкая тень */
}

/**
 * Главный заголовок приложения
 * 
 * Крупный, центрированный заголовок для брендинга
 * и идентификации приложения.
 */
h1 {
  font-size: 2.2rem;
  margin-bottom: 0.5rem;
  text-align: center;
  color: #333;
}

/**
 * Описание сервиса
 * 
 * Подзаголовок, объясняющий назначение приложения
 * для новых пользователей.
 */
.service-description {
  text-align: center;
  color: #666;
  font-size: 1.1rem;
  margin-bottom: 1.5rem;
}

/**
 * Контейнер блока ввода
 * 
 * Группирует поле ввода, заголовок и счетчик символов
 * в логическую единицу интерфейса.
 */
.input-block {
  margin-bottom: 1.5rem;
}

/**
 * Заголовки внутри блоков
 * 
 * Выделяют секции интерфейса для лучшей навигации
 * и понимания структуры приложения.
 */
.output-title {
  font-size: 1.3rem;
  font-weight: bold;
  margin-bottom: 0.5rem;
  text-align: left;
  color: black;
}

/**
 * Основное поле ввода текста
 * 
 * Крупное, удобное поле для ввода больших объемов текста
 * с моноширинным шрифтом для лучшего выравнивания результатов.
 */
.text-input-field {
  width: 100%;
  padding: 1rem;
  font-size: 1.1rem;
  border: 2px solid #ddd;
  border-radius: 8px;
  box-sizing: border-box;
  margin-bottom: 0.5rem;
  resize: vertical; /* Вертикальное изменение размера */
  min-height: 250px; /* Минимальная высота */
  font-family: monospace; /* Моноширинный шрифт для выравнивания */
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
  white-space: pre; /* Сохранение переносов строк */
}

/**
 * Стили фокуса для поля ввода
 * 
 * Визуальная индикация активного состояния поля
 * с мягким свечением и изменением цвета рамки.
 */
.text-input-field:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

/**
 * Счетчик символов
 * 
 * Информативный элемент для отслеживания лимита ввода
 * с визуальным предупреждением при приближении к максимуму.
 */
.characters-count {
  text-align: right;
  font-size: 0.9rem;
  color: #666;
  margin-bottom: 0.5rem;
}

/**
 * Предупреждение о приближении к лимиту
 * 
 * Привлекает внимание пользователя к возможному
 * превышению лимита символов.
 */
.characters-count.warn {
  color: #e67e22;
  font-weight: bold;
}

/**
 * Базовые стили для всех кнопок
 * 
 * Единообразный дизайн кнопок с современными
 * эффектами наведения и нажатия.
 */
button {
  padding: 1rem 2rem;
  font-size: 1.1rem;
  border: none;
  border-radius: 8px;
  background-color: #007bff;
  color: white;
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.1s ease;
  font-weight: 600;
}

/**
 * Стили для заблокированных кнопок
 * 
 * Визуальная индикация недоступности действия
 * с отключением интерактивных эффектов.
 */
button:disabled {
  background-color: #aaa;
  cursor: not-allowed;
  transform: none;
}

/**
 * Эффекты наведения для активных кнопок
 * 
 * Интерактивная обратная связь при наведении курсора
 * с легким приподниманием кнопки.
 */
button:hover:enabled {
  background-color: #0056b3;
  transform: translateY(-1px);
}

/**
 * Специфичные стили для кнопки очистки
 * 
 * Отличительный цвет для деструктивного действия
 * очистки пользовательского ввода.
 */
.clear-button:hover:enabled {
  background-color: #bd2130;
}

/**
 * Эффект нажатия кнопки
 * 
 * Тактильная обратная связь при активации кнопки
 * с возвращением в исходное положение.
 */
button:active:enabled {
  transform: translateY(0);
}

/**
 * Контейнер для кнопок управления
 * 
 * Горизонтальное размещение кнопок с равномерным
 * распределением пространства.
 */
.buttons-container {
  display: flex;
  gap: 1rem;
  margin-top: 0.5rem;
}

/**
 * Кнопка запуска валидации
 * 
 * Основная кнопка действия с акцентным
 * синим цветом для привлечения внимания.
 */
.submit-button {
  flex: 1;
  background-color: #007bff;
}

/**
 * Кнопка очистки
 * 
 * Вторичная кнопка с предупреждающим красным цветом
 * для деструктивного действия.
 */
.clear-button {
  flex: 1;
  background-color: #dc3545;
}

/**
 * Контейнер для отображения статистики
 * 
 * Центрированная область для краткой информации
 * о результатах валидации.
 */
.stats-container {
  margin-top: 1.5rem;
  text-align: center;
}

/**
 * Стили текста статистики
 * 
 * Выделенный текст с фоновой подсветкой
 * для лучшей видимости результатов.
 */
.stats-text {
  font-size: 1.2rem;
  font-weight: bold;
  padding: 0.5rem;
  border-radius: 8px;
  display: inline-block;
}

/**
 * Цветовые схемы для различных состояний результатов
 * 
 * Семантическое цветовое кодирование для быстрого
 * понимания результатов валидации.
 */

/* Успешные результаты - зеленый */
.stats-text.correct {
  color: #28a745;
  background-color: rgba(40, 167, 69, 0.1);
}

/* Ошибки и проблемы - красный */
.stats-text.incorrect {
  color: #dc3545;
  background-color: rgba(220, 53, 69, 0.1);
}

/* Нейтральные состояния - серый */
.stats-text.neutral {
  color: #6c757d;
  background-color: rgba(108, 117, 125, 0.1);
}

/**
 * Контейнер для индикатора Redis
 * 
 * Отдельная область для мониторинга состояния
 * инфраструктуры приложения.
 */
.redis-status-container {
  max-width: 600px;
  margin: 1rem auto 0;
  padding: 0.5rem;
  display: flex;
  justify-content: center;
}

/**
 * Стили индикатора статуса Redis
 * 
 * Компактный информационный блок с различными
 * цветовыми индикаторами состояния.
 */
.redis-status {
  text-align: center;
  font-size: 14px;
  font-weight: 600;
}

/**
 * Цветовые схемы для статусов Redis Cluster
 * 
 * Различные цвета для разных типов состояний
 * и ошибок подключения к Redis.
 */

/* Успешное подключение - зеленый */
.redis-status span.correct {
  color: green;
}

/* Состояние загрузки - желтый с анимацией */
.redis-status span.loading {
  color: #ffc107;
  animation: pulse 1.5s infinite;
}

/* Общие ошибки - красный */
.redis-status span.incorrect {
  color: red;
}

/* Сетевые ошибки - оранжевый */
.redis-status span.network-error {
  color: #ff6b35;
}

/* Серверные ошибки - темно-красный */
.redis-status span.server-error {
  color: #dc3545;
}

/* API ошибки - фиолетовый */
.redis-status span.api-error {
  color: #6f42c1;
}

/* Клиентские ошибки - оранжевый */
.redis-status span.client-error {
  color: #fd7e14;
}

/* Неизвестные ошибки - серый */
.redis-status span.unknown-error {
  color: #6c757d;
}

/**
 * Анимация пульсации для состояния загрузки
 * 
 * Привлекает внимание к процессу загрузки
 * с плавным изменением прозрачности.
 */
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

/**
 * Адаптивные стили для мобильных устройств
 * 
 * Оптимизация интерфейса для работы на смартфонах
 * и планшетах с меньшими экранами.
 */
@media (max-width: 768px) {
  .container {
    margin: 1rem;
    padding: 1.5rem;
    max-width: none;
  }

  h1 {
    font-size: 1.8rem;
  }

  /* Предотвращение зума на iOS при фокусе */
  .text-input-field {
    font-size: 16px;
  }

  button {
    padding: 0.8rem 1.5rem;
    font-size: 1rem;
  }
}
</style>