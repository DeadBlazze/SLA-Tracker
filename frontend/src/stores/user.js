import { defineStore } from 'pinia'

export const useUserStore = defineStore('user', {
    state: () => ({
        token: localStorage.getItem('token') || null,
        roleId: localStorage.getItem('role_id') 
            ? Number(localStorage.getItem('role_id')) 
            : null,
    }),
    getters: {
        // Удобный флаг авторизации для Vue Router Navigation Guard
        isAuthenticated: (state) => !!state.token,

        // Проверка суперадмина / ролей
        isSuperAdmin: (state) => state.roleId === 1,
    },
    actions: {
        login(data) {
            this.token = data.token
            this.roleId = Number(data.role_id)

            // Сохраняем в localStorage отдельными ключами
            localStorage.setItem('token', this.token)
            localStorage.setItem('role_id', String(this.roleId))
        },

        logout() {
            // Удаляем именно те ключи, которые создавали
            localStorage.removeItem('token')
            localStorage.removeItem('role_id')

            // Сбрасывает state к начальным значениям (null)
            this.$reset()
        }
    }
})