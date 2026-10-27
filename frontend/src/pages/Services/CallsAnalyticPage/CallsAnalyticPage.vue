<template lang="pug">
.calls-analytic.topMargin40
    .wrapper
        h1 Аналитика звонков
        .calls-analytic__sections
            section.filter
                h2.filter__title Период отчета
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
                    .phones-filter
                        p Номера телефонов
                        .chips-group
                            label.chip(
                                v-for="phone in phoneList" 
                                :key="phone.id"
                            )
                                input.chip__input(
                                    type="checkbox" 
                                    :value="phone.id" 
                                    v-model="selectedPhones"
                                )
                                span.chip__phone 8{{ phone.id }}
                                span.chip__badge {{ phone.source }}
                    button.button--primary(
                        @click="fetchCallAnalytic" 
                        :disabled="!isDatesValid"
                    ) Загрузить
</template>
<script>
import dayjs from 'dayjs';
import { api } from '@/api/api';
import { useUserStore } from '@/stores/user'
export default {
    data() {
        return {
            userStore: useUserStore(),
            selectedPhones: [],
            phoneList: [
                { id: '9210802077', source: 'ВК' },
                { id: '9210812077', source: '2ГИС' },
                { id: '9212482866', source: 'ЯБизнес' },
                { id: '9532639075', source: 'ЯУслуги2' }
            ],
            dateFrom: '', // Формат YYYY-MM-DD
            dateTo: '',
            history: null,
            loadStatus: false,
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
        async fetchCallAnalytic(){
            if (!this.isDatesValid) return;
            const datetimeFrom = dayjs(this.dateFrom).startOf('day').format('YYYY-MM-DDTHH:mm:ssZ');
            const datetimeTo = dayjs(this.dateTo).endOf('day').format('YYYY-MM-DDTHH:mm:ssZ');
            let payload = {
                datetimeFrom: datetimeFrom,
                datetimeTo: datetimeTo,
                phones: this.selectedPhones
            }
            try {
                let result = await api.post('/dashboard/get-calls-analytic', payload, {
                    headers: {
                        Authorization: `Bearer ${this.userStore.token}`
                    }
                });
                this.history = result.data;
                console.log('Данные успешно загружены:', result);
            } catch (error) {
                console.error('Ошибка при загрузке аналитики:', error);
            }finally{
                this.loadStatus = false
            }
        },  
        resetSelection() {
            this.selectedPhones = [];
        }
    },
    mounted() {
        // Локальное время
        const todayStr = dayjs().format('YYYY-MM-DD');

        this.dateFrom = todayStr;
        this.dateTo = todayStr;
    }   
}
</script>
<style lang="scss" scoped>
@use './CallsAnalyticPage.scss' as *;
</style>