<template lang="pug">
Header
router-view
.themeSwitch
  button(@click="setTheme('dark')") тёмная тема
  button(@click="setTheme('light')") светлая тема
</template>
<script>
import Header from './components/ui/header/header.vue';
export default {
  components: {
    Header: Header
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

<style scoped></style>
