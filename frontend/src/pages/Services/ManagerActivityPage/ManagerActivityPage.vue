<template lang="pug">
.manager-activity.topMargin40
    .wrapper
        h1 Сводка по менеджерам
        
        .settings-page__sections
            section.settings-section
                h2.settings-section__title Период отчета
                p.settings-section__desc Выберите даты для загрузки логов из базы
                .settings-section__content
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
                        
                        button.button--primary(
                            @click="fetchActivity" 
                            :disabled="!isDatesValid"
                        ) Загрузить
                        
                        span.error(v-if="!isDatesValid") ошибка
            // Блок для вывода ответа
            section.activity-section(v-if="activityData")
                h2.activity-section__title Результаты
                .activity-section__content
                    .manager-card(v-for="(statuses, managerName) in activityData" :key="managerName")
                        h3.manager-card__name {{ managerName }}
                        
                        .manager-card__statuses
                            .status-group(v-for="(leads, statusName) in statuses" :key="statusName")
                                .status-group__header
                                    span.status-group__name {{ statusName }}
                                    span.status-group__badge {{ leads.length }}
                                
                                ul.status-group__list
                                    li.lead-item(v-for="lead in leads" :key="lead.id")
                                        a(:href="`https://tkdvina.amocrm.ru/leads/detail/${lead.amo_lead_id}`" target="_blank")
                                            span.lead-item__id ID сделки: {{ lead.amo_lead_id }}
                                        span.lead-item__time {{ new Date(lead.entered_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) }}

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
            activityData: null, // Сюда сохраним ответ от сервера
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

            // Формируем timestamp для начала дня (00:00:00)
            const fromDate = new Date(`${this.dateFrom}T00:00:00`);
            const timestampFrom = Math.floor(fromDate.getTime() / 1000);

            // Формируем timestamp для конца дня (23:59:59)
            const toDate = new Date(`${this.dateTo}T23:59:59`);
            const timestampTo = Math.floor(toDate.getTime() / 1000);
            
            let payload = {
                timestampFrom: timestampFrom,
                timestampTo: timestampTo
            }
            try {
                let result = await api.post('/dashboard/get-managers-activity', payload, {
                    headers: {
                        Authorization: `Bearer ${this.userStore.token}`
                    }
                });
                
                this.activityData = result.data[0];
                console.log('Данные успешно загружены:', result);
                
            } catch (error) {
                console.error('Ошибка при загрузке активности:', error);
            }
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