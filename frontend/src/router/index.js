import { createRouter, createWebHistory } from "vue-router";
import IndexPage from "@/components/pages/IndexPage/IndexPage.vue";
import RegisterPage from "@/components/pages/Register/RegisterPage.vue";
import LoginPage from "@/components/pages/Login/LoginPage.vue";
const routes = [
  { path: '/', component: IndexPage },
  { path: '/register', component: RegisterPage},
  { path: '/login', component: LoginPage}
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})


