import { createRouter, createMemoryHistory } from 'vue-router'
import IndexPage from '@/components/pages/IndexPage.vue'
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    {
      path: '/',
      component:IndexPage 
    }
  ],
})

export default router
