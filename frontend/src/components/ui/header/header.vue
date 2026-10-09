<template lang="pug">
    header
        .header__inner
            router-link(to="/") 
                h1 Dvina workspace
            ProfileIcon.profile-icon
</template>
<script>
import ProfileIcon from '@/assets/images/icons/profile.svg?component';
export default{
    components: {
        ProfileIcon: ProfileIcon
    }
}
</script>
<style lang="scss">
@use './header.scss' as *;
</style>