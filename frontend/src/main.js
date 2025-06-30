// Импортируем функцию createApp из библиотеки Vue
import {createApp} from 'vue'
// Импортируем корневой компонент приложения (App.vue)
import App from './App.vue'

// Создаём экземпляр приложения Vue и монтируем его в элемент с id 'app' в index.html
createApp(App).mount('#app')

/*
Подробности:
- createApp(App): создаёт новое Vue-приложение, где App — это корневой компонент
- mount('#app'): указывает, что приложение должно быть привязано (смонтировано) к элементу с id="app" в HTML-документе
*/
