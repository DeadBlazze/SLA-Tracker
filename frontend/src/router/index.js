import { createRouter, createWebHistory } from "vue-router";
import IndexPage from "@/components/pages/IndexPage/IndexPage.vue";
import RegisterPage from "@/components/pages/Register/RegisterPage.vue";
import LoginPage from "@/components/pages/LoginPage/LoginPage.vue";
import DashboardPage from "@/components/pages/DashboardPage/DashboardPage.vue";
import { useUserStore } from "@/stores/user";

const routes = [
  { path: '/', component: LoginPage },
  { path: '/dashboard', component: DashboardPage, meta: { requiresAuth: true}},
  { path: '/register', component: RegisterPage},
  { path: '/login', component: LoginPage}
]

const router = createRouter({
  history: createWebHistory(),
  routes: routes,
})

router.beforeEach(async (to, from, next) => {
  const userStore = useUserStore()

  if(!to.meta.requiresAuth) return next()
  if(!userStore.isAuthenticated || !userStore.roleId) return next('/login')

  return next()
})

export default router; 


