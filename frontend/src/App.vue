<template lang="pug">
  Header
  router-view 
</template>
<script>
import Header from './components/ui/header/header.vue';
export default {
  components: {
    Header: Header
  }
}
</script>

<style scoped></style>
