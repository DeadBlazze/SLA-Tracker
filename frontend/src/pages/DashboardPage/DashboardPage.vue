<template lang="pug">
.DashboardPage
    .wrapper
        .services
            .service
                .service__header
                    h3.service__title Аналитика рекламы
                    ArrowTop.service__arrow(@click="")
                p Сводный дашборд эффективности маркетинга с группировкой по рекламным источникам.
            .service
                .service__header
                    h3.service__title Аналитика звонков
                    ArrowTop.service__arrow
                p Статистика звонков по рекламным номерам компании
            .service
                .service__header
                    h3.service__title Активность менеджеров
                    router-link(:to="{name: 'dashboard.manager-activity'}") 
                        ArrowTop.service__arrow
                p Инструмент оценки ежедневной производительности сотрудников отдела продаж.
            .service
                .service__header
                    h3.service__title Зависшие лиды
                    ArrowTop.service__arrow
                p Оперативный радар сделок, превысивших нормативное время пребывания на этапах воронки.
            .service
                .service__header
                    h3.service__title интеграция МегаФон АТС
                    ArrowTop.service__arrow
                p Технический шлюз управления интеграцией АТС и calltrackingone
</template>
<script>
import ArrowTop from "@/assets/images/icons/arrowTop.svg?component";
import { useUserStore } from '@/stores/user';
import axios from "axios";
export default{
    data(){
        return {
            userStore: useUserStore()
        }
    },
    components: {
        ArrowTop: ArrowTop
    },
    methods:{

    }
}
</script>
<style lang="scss" scoped>
@use './DashboardPage.scss' as *;
</style>