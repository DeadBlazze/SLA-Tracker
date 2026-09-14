import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router/index.js'
import './assets/styles/global/index.scss'

const app = createApp(App)

app.use(createPinia())
app.use(router)

let appInstance = null

function mountApp() {
  const container = document.getElementById('my-vue-widget-root')

  // Проверяем, что элемент реально появился в разметке amoCRM
  // и что приложение ещё не смонтировано
  if (container && !appInstance) {
    appInstance = createApp(App)
    appInstance.mount(container)
  }
}

// 1. Пытаемся смонтировать сразу (если DOM уже успел вставиться)
mountApp()

// 2. Если контейнера ещё нет, ждём его появления через MutationObserver
const observer = new MutationObserver(() => {
  const container = document.getElementById('my-vue-widget-root')
  if (container) {
    mountApp()
    observer.disconnect() // Отключаем слежение, как только смонтировали
  }
})

// Начинаем следить за добавлением элементов на страницу
observer.observe(document.body, {
  childList: true,
  subtree: true
})
