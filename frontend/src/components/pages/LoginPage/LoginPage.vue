<template lang="pug">
.LoginPage
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
                button.button--primary Войти
</template>
<script>
export default {
    data(){
        return {
            form: {},
            errors: {}
            }
    }
}
</script>
<style lang="scss" scoped>
@use './LoginPage.scss' as *;
</style>