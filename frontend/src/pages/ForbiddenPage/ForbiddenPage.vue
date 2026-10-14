<template lang="pug">
.ForbiddenPage.topMargin
  .ForbiddenPage__card
    .ForbiddenPage__code 403
    h1.ForbiddenPage__title Доступ ограничен
    p.ForbiddenPage__description У вашего аккаунта недостаточно прав для просмотра этого раздела.
    
    .ForbiddenPage__actions
        button.ForbiddenPage__btn.ForbiddenPage__btn--secondary(@click="goBack") Назад
        button.ForbiddenPage__btn.ForbiddenPage__btn--primary(@click="this.$router.push('/dashboard')") На дашборд
</template>

<script>
export default {
  name: 'ForbiddenPage',
  methods: {
    goBack() {
      if (window.history.length > 1) {
        this.$router.back()
      } else {
        this.$router.push('/dashboard')
      }
    }
  }
}
</script>

<style lang="scss" scoped>
@use './ForbiddenPage.scss' as *;
</style>