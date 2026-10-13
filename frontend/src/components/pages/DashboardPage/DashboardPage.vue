<template lang="pug">
.DashboardPage
    .wrapper
        .account
            .account__preview
                p {{roleAlias}}
                p {{profilePreview}}
        .services
            .service
                .service__header
                    h3.service__title Аналитика рекламы
                    router-link(:to="{name: 'dashboard.manager-activity'}") 
                        ArrowTop.service__arrow
                p Сводный дашборд эффективности маркетинга с группировкой по рекламным источникам.

            .service
                .service__header
                    h3.service__title Зависшие лиды
                    ArrowTop.service__arrow
                p Оперативный радар сделок, превысивших нормативное время пребывания на этапах воронки.
            .service
                .service__header
                    h3.service__title Активность менеджеров
                    ArrowTop.service__arrow
                p Инструмент оценки ежедневной производительности сотрудников отдела продаж.
            .service
                .service__header
                    h3.service__title интеграция МегаФон АТС
                    ArrowTop.service__arrow
                p Технический шлюз управления интеграцией АТС и calltrackingone
</template>
<script>
import ArrowTop from "@/assets/images/icons/arrowTop.svg?component";
import { useUserStore } from '@/stores/user';
export default{
    data(){
        return {
            userStore: useUserStore()
        }
    },
    components: {
        ArrowTop: ArrowTop
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
@use './DashboardPage.scss' as *;
</style>