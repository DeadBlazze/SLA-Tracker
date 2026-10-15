<template lang="pug">
.roleAliasPanel
    .account__preview(v-if="roleAlias || profilePreview")
        p {{roleAlias}}
        p {{profilePreview}}
</template>
<script>
import { useUserStore } from '@/stores/user';
export default{
    data(){
        return {
            userStore: useUserStore()
        }
    },
    computed: {
        profilePreview() {
            return this.userStore.preview
        },
        roleAlias() {
            return this.userStore.roleAlias
        }
    }
}
</script>
<style lang="scss" scoped>
@use './RoleAliasPanel.scss' as *;
</style>