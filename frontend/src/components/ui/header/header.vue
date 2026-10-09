<template lang="pug">
    header
        .header__inner
            router-link(to="/") 
                h1 SLA-Tracker
            .theme
                button(@click="setTheme('dark')") тёмная тема
                button(@click="setTheme('light')") светлая тема
            ProfileIcon.profile-icon
</template>
<script>
import ProfileIcon from '@/assets/images/icons/profile.svg?component';
export default{
    components: {
        ProfileIcon: ProfileIcon
    },
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
@use './header.scss' as *;
</style>