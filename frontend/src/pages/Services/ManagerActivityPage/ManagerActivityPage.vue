<template lang="pug">
.manager-activity.topMargin40
    .wrapper
        h1 Сводка по менеджерам
        
        .manager-activity__sections
            section.filter
                h2.filter__title(@click="console.log(activityData)") Период отчета
                p.filter__desc Выберите даты для загрузки логов из базы
                .filter__content
                    .date-filter
                        .date-filter__inputs
                            .date-filter__group
                                label(for="date-from") От:
                                input(
                                    type="date" 
                                    id="date-from" 
                                    v-model="dateFrom"
                                    class="ui-input"
                                )
                            .date-filter__group
                                label(for="date-to") До:
                                input(
                                    type="date" 
                                    id="date-to" 
                                    v-model="dateTo"
                                    class="ui-input"
                                )
                    .group-filter
                        p Группировать по:
                        button(@click="changeGroupby('status')" :class="{'active': groupBy === 'status'}") статусам
                        button(@click="changeGroupby('leadId')" :class="{'active': groupBy === 'leadId'}") ID сделок
                    button.button--primary(
                        @click="fetchActivity" 
                        :disabled="!isDatesValid"
                    ) Загрузить
                        
                    span.error(v-if="!isDatesValid") ошибка
            // Блок для вывода ответа
            section.stats(v-if="activityData?.status === 'ok' && loadStatus === false")
                h2.stats__title Результаты
                .stats__content
                    .manager-card.byStatus(v-if="groupBy === 'status'" v-for="(statuses, managerName) in activityData.data" :key="managerName")
                        h3.manager-card__name {{ managerName }}
                        
                        
                        .manager-card__statuses
                            .status-group(v-for="(leads, statusName) in statuses" :key="statusName")
                                .status-group__header(@click="toggleVisiblity(`${managerName}_${statusName}`)")
                                    span.status-group__name {{ statusName }}
                                    span.status-group__badge {{ leads.length }}
                                
                                
                                transition(name="fade")
                                    ul.status-group__list(v-if="statusesToShow[`${managerName}_${statusName}`]")
                                        li.lead-item(v-for="lead in leads" :key="lead.id")
                                            a(:href="`https://tkdvina.amocrm.ru/leads/detail/${lead.amo_lead_id}`" target="_blank")
                                                span.lead-item__id ID: {{ lead.amo_lead_id }}
                                            span.lead-item__time {{ new Date(lead.entered_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) }}
                    .manager-card.byLead(v-if="groupBy === 'leadId'" v-for="(leadIds, managerName) in activityData" :key="managerName")
                        h3.manager-card__name {{ managerName }}

                        .manager-card__leads
                            .manager-card_lead(v-for="(statuses, leadId) in leadIds" :key="leadId")
                                .lead__header
                                    span.title ID: {{leadId}}
                                ul.lead__statuses
                                    li.status(v-for="(statusData, index) in statuses" :key="statusData.amo_lead_id + statusData.status_id" :class="{'last' : index === statuses.length - 1}")
                                        a(:href="`https://tkdvina.amocrm.ru/leads/detail/${statusData.amo_lead_id}`" target="_blank")
                                            span.lead-item__id {{ statusData.label }}
                                        span.lead-item__time {{ new Date(statusData.entered_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) }}
            h3(v-if="loadStatus === 'start'") Загрузка
            h3(v-if="loadStatus === false && activityData?.status === 'error'") {{activityData?.message}}
</template>

<script>
import { api } from '@/api/api';
import { useUserStore } from '@/stores/user'
export default {
    name: 'ManagerActivityPage',
    data() {
        return {
            userStore: useUserStore(),
            dateFrom: '', // Формат YYYY-MM-DD
            dateTo: '',   // Формат YYYY-MM-DD
            groupBy: 'status',
            activityData: null, // Сюда сохраним ответ от сервера
            loadStatus: false,
            statusesToShow: []
        }
    },
    computed: {
        isDatesValid() {
            if(this.dateFrom !== '' && this.dateTo !== ''){
                // Используем <=, чтобы разрешить выбор одного и того же дня
                if(new Date(this.dateFrom) <= new Date(this.dateTo)){
                    return true
                }
            }
            return false
        }
    },
    methods: {
        async fetchActivity() {
            if (!this.isDatesValid) return;
            this.loadStatus = 'start';
            // Формируем timestamp для начала дня (00:00:00)
            const fromDate = new Date(`${this.dateFrom}T00:00:00`);
            const timestampFrom = Math.floor(fromDate.getTime() / 1000);

            // Формируем timestamp для конца дня (23:59:59)
            const toDate = new Date(`${this.dateTo}T23:59:59`);
            const timestampTo = Math.floor(toDate.getTime() / 1000);
            
            let payload = {
                timestampFrom: timestampFrom,
                timestampTo: timestampTo,
                groupBy: this.groupBy
            }
            try {
                let result = await api.post('/dashboard/get-managers-activity', payload, {
                    headers: {
                        Authorization: `Bearer ${this.userStore.token}`
                    }
                });
                this.activityData = result.data;
                console.log('Данные успешно загружены:', result);
                
            } catch (error) {
                console.error('Ошибка при загрузке активности:', error);
            }finally{
                this.loadStatus = false
            }
        },
        changeGroupby(entity){
            this.groupBy = entity
            this.activityData = null
        },
        toggleVisiblity(key){
            this.statusesToShow[key] = !this.statusesToShow[key]
        }
    },
    mounted() {
        // При загрузке страницы по умолчанию ставим текущий день
        const today = new Date();
        
        // Корректируем timezone offset для правильного локального отображения даты
        const todayStr = new Date(today.getTime() - (today.getTimezoneOffset() * 60000)).toISOString().split('T')[0];
        
        this.dateFrom = todayStr;
        this.dateTo = todayStr;
    }   
}
</script>
<style lang="scss" scoped>
@use './ManagerActivityPage.scss' as *;
</style>