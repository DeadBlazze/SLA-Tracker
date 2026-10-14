<template lang="pug">
Header
SubHeader
router-view
</template>
<script>
import Header from './components/layout/Header/Header.vue';
import SubHeader from '@/components/ui/SubHeader/SubHeader.vue';
export default {
  components: {
    Header: Header,
    SubHeader: SubHeader
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
