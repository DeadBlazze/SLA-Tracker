<template lang="pug">
.indexPage
    .wrapper
        .account
            .account__preview
                p Старцев Евгений
                p Администратор
        .services
            .service
                .service__header
                    h3.service__title Аналитика рекламы
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
export default{
    components: {
        ArrowTop: ArrowTop
    }
}
</script>
<style lang="scss" scoped>
@use './IndexPage.scss' as *;
</style>