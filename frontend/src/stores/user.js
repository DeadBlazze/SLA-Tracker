import { defineStore } from 'pinia'

export const useUserStore = defineStore('user', {
    state: () => ({
        token: localStorage.getItem('token') || null,
        roleId: Number(localStorage.getItem('role_id')) || null,
        preview: localStorage.getItem('preview') || null,
        roleAlias: localStorage.getItem('roleAlias') || null
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
            this.preview = `${data.surname} ${data.name}`
            this.roleAlias = data.roleAlias
            // Сохраняем в localStorage отдельными ключами
            localStorage.setItem('token', this.token)
            localStorage.setItem('role_id', Number(this.roleId))
            localStorage.setItem('preview', String(this.preview))
            localStorage.setItem('roleAlias', String(this.roleAlias))
            localStorage.setItem('user_id', String(data.user_id))
        },

        logout() {
            // Удаляем именно те ключи, которые создавали
            localStorage.removeItem('token')
            localStorage.removeItem('role_id')
            localStorage.removeItem('preview')
            localStorage.removeItem('roleAlias')
            localStorage.removeItem('user_id')
            // Сбрасывает state к начальным значениям (null)
            this.$reset()
        }
    }
})