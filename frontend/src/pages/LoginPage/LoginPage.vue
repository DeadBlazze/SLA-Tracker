<template lang="pug">
.LoginPage.topMargin
    form.form--main
        h2.form__title Авторизация
        .form__content
            .errorContainer
                .inputsGroup
                    label(for="email") Почта
                    input.textInput--main(type="text" placeholder="example@mail.com" id="email" v-model="form.email")
                span.error(v-if="errors.email") {{ errors.email }}
            .errorContainer
                .inputsGroup
                    label(for="password") Пароль
                    input.textInput--main(type="password" placeholder="" id="password" v-model="form.password")
                span.error(v-if="errors.password") {{ errors.password }}
            .form__submit
                button.button--primary(type="submit" @click.prevent='handleLogin') Войти
</template>
<script>
import { api } from '@/api/api';
import { toast } from 'vue3-toastify'
import { useUserStore } from '@/stores/user';
export default {
    data(){
        return {
            form: {},
            errors: {},
            userStore: useUserStore()
        }
    },
    methods: {
        validateForm() {
            this.errors = {}
            let valid = true

            const password = this.form.password
            if (!(password?.length >= 8 && password?.length <= 100)) {
                this.errors.password = 'Пароль должен содержать от 8 до 100 символов'
                valid = false
            }else if (/\s/.test(password)){
                this.errors.password = 'Пароль не должен содержать пробелы'
                valid = false
            }else if (!/[^A-Za-z0-9]/.test(password)) {
                this.errors.password = 'Пароль должен содержать спецсимволы'
                valid = false
            }else if(!/^(?=.*[.!,?])[0-9A-Za-z\s.!,?-]{8,100}$/.test(password)){
                this.errors.password = 'Введите пароль латиницей'
                valid = false
            }

            if(!/^[0-9A-Za-z_.+-]{1,64}@[0-9A-Za-z_-]{1,10}\.[0-9A-Za-z_-]{2,6}$/.test(this.form.email)){
                this.errors.email = 'Некорректная почта'
                valid = false
            }

            return valid
        },
        async handleLogin(){
            if(!this.validateForm()){
                return
            }
            this.errors = {}
            const payload = {login: this.form.email, password: this.form.password}
            try {
                const response = await api.post('/dashboard/login', payload)
                const data = response?.data

                // Проверяем наличие токена и role_id
                if (!data?.token || !data?.role_id) {
                    toast.error({ title: 'Ошибка', content: 'Некорректный ответ сервера: отсутствует токен или роль' })
                    return
                }

                this.userStore.login(data)
                this.$router.push('/dashboard')
            } catch (e) {
                if (axios.isAxiosError(e)) {
                    toast.error({ title: 'Ошибка', content: e.response?.data?.error || 'Ошибка авторизации' })
                    console.error(e)
                } else {
                    toast.error({ title: 'Ошибка', content: 'Неизвестная ошибка' })
                    console.error(e)
                }
            }
        }
    }
}
</script>
<style lang="scss" scoped>
@use './LoginPage.scss' as *;
</style>