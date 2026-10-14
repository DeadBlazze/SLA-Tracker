<template lang="pug">
Header
Breadcrumb
router-view
</template>
<script>
import Header from './components/layout/Header/Header.vue';
import Breadcrumb from './components/ui/Breabcrumb/Breadcrumb.vue';
export default {
  components: {
    Header: Header,
    Breadcrumb: Breadcrumb
  },
  data() {
    return {

    }
  },
  mounted() {
    this.initTheme()
  },
  methods: {
    initTheme() {
      const savedTheme = localStorage.getItem('user-theme')
      if (savedTheme) {
        document.documentElement.dataset.theme = savedTheme
      } else if(window.matchMedia('(prefers-color-scheme: dark)').matches){
        document.documentElement.dataset.theme = 'dark'
      }
      else {
        // Инициализируем дефолтную тему на теге <html>
        document.documentElement.dataset.theme = 'light'
      }
    }
  }
}
</script>

<style lang="scss">
@use './App.scss' as *;
</style>
