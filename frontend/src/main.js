import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from '@/router/index.js'
import './assets/styles/index.scss'
import Vue3Toastify from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const pinia = createPinia()

createApp(App).use(pinia).use(router)
.use(Vue3Toastify, {
  autoClose: 3000,
  position: 'top-center',
  theme: 'dark'
})
.mount('#app')
