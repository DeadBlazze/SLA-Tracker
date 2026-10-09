<template lang="pug">
    .header__inner
        router-link(to="/") 
            h1 SLA-Tracker
        router-link(to="/login") вход
        router-link(to="/register") регистрация
        .theme
            button(@click="setTheme('dark')") тёмная тема
            button(@click="setTheme('light')") светлая тема
</template>
<script>
    export default{
        data() {
            return {
            // Начальное значение темы
            currentTheme: 'light'
            }
        },
        watch: {
            currentTheme(newTheme) {
                document.documentElement.dataset.theme = newTheme
                localStorage.setItem('user-theme', newTheme)
            }
        },
        mounted() {
            const savedTheme = localStorage.getItem('user-theme')
            
            if (savedTheme) {
                this.currentTheme = savedTheme
            } else if(window.matchMedia('(prefers-color-scheme: dark)').matches){
                this.currentTheme = 'dark'
            }
            else {
                // Инициализируем дефолтную тему на теге <html>
                document.documentElement.dataset.theme = this.currentTheme
            }
        },
        methods: {
            setTheme(theme) {
            this.currentTheme = theme
            }
        }
    }
</script>
<style lang="scss">
    .header__inner{
        display: flex;
        padding: 15px 20px;
        align-items: center;
        justify-content: space-between;
    }
</style>