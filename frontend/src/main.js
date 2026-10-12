import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from '@/router/index.js'
import './assets/styles/index.scss'

const pinia = createPinia()

createApp(App).use(pinia).use(router).mount('#app')
