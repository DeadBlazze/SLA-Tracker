<template lang="pug">
    header
        .header__inner
            router-link(to="/") 
                h1 Dvina workspace
            ProfileIcon.profile-icon(@click="toggleSettingsModal")
        .modal-backdrop(@click="modalOpen = false" v-if="modalOpen")
            .settingsModal
                template(v-if="!isAuthorized")
                    p Войдите в аккаунт
                template(v-else)
                    router-link(to="/settings" @click="modalOpen = false") Настройки
                    button(@click="logout") Выйти
</template>
<script>
import ProfileIcon from '@/assets/images/icons/profile.svg?component';
import { useUserStore } from '@/stores/user';
export default{
    components: {
        ProfileIcon: ProfileIcon
    },
    data(){
        return{
            userStore: useUserStore(),
            modalOpen: false
        }
    },  
    methods: {
        logout(){
            this.userStore.logout()
            this.$router.push('/login')
        },
        toggleSettingsModal(){
            this.modalOpen = !this.modalOpen
        }
    },
    computed: {
        isAuthorized() {
            return this.userStore.isAuthenticated
        }
    },
    watch: {
        $route() {
            this.modalOpen = false;
        }
    }
}
</script>
<style lang="scss">
@use './header.scss' as *;
</style>