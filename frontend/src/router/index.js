import { createRouter, createWebHistory } from "vue-router";
import IndexPage from "@/components/pages/IndexPage/IndexPage.vue";
import RegisterPage from "@/components/pages/Register/RegisterPage.vue";
import LoginPage from "@/components/pages/LoginPage/LoginPage.vue";
import DashboardPage from "@/components/pages/DashboardPage/DashboardPage.vue";
import ForbiddenPage from "@/components/pages/ForbiddenPage/ForbiddenPage.vue";
import { useUserStore } from "@/stores/user";

export const ROLES = {
  SUPER_ADMIN: 1,
  MARKETER: 2,
  SALES_MANAGER: 3
}

const routes = [
  { path: '/', redirect: '/login'},
  { path: '/dashboard', component: DashboardPage, 
    meta: { requiresAuth: true}, roles: [ROLES.SUPER_ADMIN, ROLES.MARKETER, ROLES.SALES_MANAGER]},
  { path: '/register', component: RegisterPage},
  { path: '/login', component: LoginPage},
  { path: '/forbidden', component: ForbiddenPage}
]

const router = createRouter({
  history: createWebHistory(),
  routes: routes,
})

router.beforeEach(async (to, from) => {
  const userStore = useUserStore()

  if(!to.meta.requiresAuth) return true
  if(!userStore.isAuthenticated || !userStore.roleId) return '/login'
  if (to.meta.roles){
    const hasRole = to.meta.roles.includes(Number(userStore.roleId))
    if (!hasRole) {
      // Если роли нет, отправляем либо на 403 страницу, либо на общий дашборд
      return '/forbidden'
    }
  }
  return true
})
router.afterEach((to) => {
  document.title = to.meta.title || 'Dvina workspace'
})
export default router; 


