<template lang="pug">
.subHeader
    Breadcrumb
    RoleAliasPanel
</template>
<script>

import Breadcrumb from '@/components/ui/Breabcrumb/Breadcrumb.vue';
import RoleAliasPanel from '@/components/ui/RoleAliasPanel/RoleAliasPanel.vue';
export default {
    components: {
        Breadcrumb: Breadcrumb,
        RoleAliasPanel: RoleAliasPanel
    }
}
</script>
<style lang="scss" scoped>
@use './SubHeader.scss' as *;
</style>