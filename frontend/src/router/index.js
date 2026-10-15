import { createRouter, createWebHistory } from "vue-router";
import IndexPage from "@/pages/IndexPage/IndexPage.vue";
import RegisterPage from "@/pages/RegisterPage/RegisterPage.vue";
import LoginPage from "@/pages/LoginPage/LoginPage.vue";
import DashboardPage from "@/pages/DashboardPage/DashboardPage.vue";
import ForbiddenPage from "@/pages/ForbiddenPage/ForbiddenPage.vue";
import SettingsPage from "@/pages/SettingsPage/SettingsPage.vue";
import ManagerActivityPage from "@/pages/Services/ManagerActivityPage/ManagerActivityPage.vue";
import { useUserStore } from "@/stores/user";

export const ROLES = {
  SUPER_ADMIN: 1,
  MARKETER: 2,
  SALES_MANAGER: 3
}

const routes = [
  { path: '/', redirect: '/dashboard', meta: {breadcrumb: "Главная"}},
  { 
    path: '/dashboard',
    meta: {breadcrumb : "Дашборд"},
    children: [
      {
        path: '',
        name: 'dashboard',
        component: DashboardPage,
        meta: { 
          roles: [ROLES.SUPER_ADMIN, ROLES.MARKETER, ROLES.SALES_MANAGER],
          requiresAuth: true,
        }
      },
      {
        path: 'manager-activity',
        name: 'dashboard.manager-activity',
        component: ManagerActivityPage,
        meta: { 
          roles: [ROLES.SUPER_ADMIN, ROLES.MARKETER] ,
          requiresAuth: true,
          breadcrumb: "Активность менеджеров" 
        }
      }
    ]
  },
  { path: '/register', component: RegisterPage},
  { path: '/login', component: LoginPage, meta: {breadcrumb : "Авторизация"}},
  { path: '/settings', component: SettingsPage, meta: {breadcrumb : "Настройки"}},
  { path: '/forbidden', component: ForbiddenPage, meta: {breadcrumb : "forbidden"}}
]

const router = createRouter({
  history: createWebHistory(),
  routes: routes,
})

router.beforeEach(async (to, from) => {
  const userStore = useUserStore()
  const targetRoute = to.matched.at(-1)

  if(!targetRoute?.meta?.requiresAuth) return true
  if(!userStore.isAuthenticated || !userStore.roleId) return '/login'
  
  const allowedRoles = targetRoute?.meta?.roles
  if (allowedRoles && !allowedRoles.includes(userStore.roleId)) {
    return '/forbidden'
  }
  return true
})
router.afterEach((to) => {
  document.title = to.meta.title || 'Dvina workspace'
})
export default router; 


