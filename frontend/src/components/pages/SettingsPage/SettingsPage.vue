<template lang="pug">
.settings-page
    .settings-page__inner
        h1 Настройки

        .settings-page__sections
            // Раздел оформления (с переключением темы)
            section.settings-section
                h2.settings-section__title Оформление
                p.settings-section__desc Настройте внешний вид рабочей области
                .settings-section__content
                    .theme-switcher
                        button.button--primary(
                            :class="{ 'button--active': currentTheme === 'light' }"
                            @click="setTheme('light')"
                        ) Светлая тема
                        button.button--primary(
                            :class="{ 'button--active': currentTheme === 'dark' }"
                            @click="setTheme('dark')"
                        ) Тёмная тема

            // Заглушка: Профиль
            section.settings-section
                h2.settings-section__title Профиль
                p.settings-section__desc Управление личными данными и аккаунтом
                .settings-section__content
                    .settings-section__stub
                        p Раздел находится в разработке

            // Заглушка: Уведомления
            section.settings-section
                h2.settings-section__title Уведомления
                p.settings-section__desc Настройка почтовых и системных оповещений
                .settings-section__content
                    .settings-section__stub
                        p Раздел находится в разработке

            // Заглушка: Безопасность
            section.settings-section
                h2.settings-section__title Безопасность
                p.settings-section__desc Смена пароля, сессии
                .settings-section__content
                    .settings-section__stub
                        p Раздел находится в разработке
</template>
<script>
export default {
    name: 'SettingsPage',
    data() {
        return {
            currentTheme: 'light'
        }
    },
    mounted() {
        this.currentTheme = document.documentElement.dataset.theme || 'light'
    },
    methods: {
        setTheme(theme) {
            this.currentTheme = theme
            document.documentElement.dataset.theme = theme
            // Единый ключ 'user-theme'
            localStorage.setItem('user-theme', theme)
        }
    }
}
</script>
<style lang="scss">
@use './SettingsPage.scss' as *;
</style>