import { createRouter, createWebHistory } from 'vue-router'
import IndexPage from '@/components/pages/IndexPage.vue'
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component:IndexPage 
    }
  ],
})

export default router
