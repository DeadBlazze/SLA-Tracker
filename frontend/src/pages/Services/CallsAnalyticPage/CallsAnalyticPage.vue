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
                        :disabled="!isDatesValid || !isPhoneChoosen"
                    ) Загрузить
            section.stats
                .stats__empty(v-if="!history && !loadStatus")
                    p Выберите фильтры и нажмите «Загрузить» для построения аналитики.
                
                .stats__loading(v-else-if="loadStatus")
                    .spinner
                    p Идет сбор данных...

                .stats__content(v-else-if="chartData.length")
                    .stats__header
                        .stats__summary
                            .summary-card
                                span.summary-card__label Всего звонков
                                strong.summary-card__value {{ totalStats.total }}
                            .summary-card
                                span.summary-card__label Успешные
                                strong.summary-card__value.summary-card__value--success {{ totalStats.success }}
                            .summary-card
                                span.summary-card__label Пропущенные
                                strong.summary-card__value.summary-card__value--missed {{ totalStats.missed }}

                        .stats__controls
                            span.controls__label Группировка:
                            .btn-group
                                button.btn-group__btn(
                                    :class="{ 'btn-group__btn--active': groupBy === 'day' }"
                                    @click="groupBy = 'day'"
                                ) Дни
                                button.btn-group__btn(
                                    :class="{ 'btn-group__btn--active': groupBy === 'week' }"
                                    @click="groupBy = 'week'"
                                ) Недели
                                button.btn-group__btn(
                                    :class="{ 'btn-group__btn--active': groupBy === 'month' }"
                                    @click="groupBy = 'month'"
                                ) Месяцы

                    //- Сам график
                    .stats__chart-wrapper(@mouseleave="hideTooltip")
                        //- Единый плавающий тултип поверх всего
                        .chart-tooltip(
                            v-if="tooltip.visible"
                            :style="{ top: tooltip.y + 'px', left: tooltip.x + 'px' }"
                        )
                            span {{ tooltip.label }}
                            small Всего: {{ tooltip.total }} (✅ {{ tooltip.success }} / ❌ {{ tooltip.missed }})
                        .stats__chart
                            .chart-column(
                                v-for="item in chartData" 
                                :key="item.key"
                                @mouseenter="showTooltip($event, item)"
                            )
                                span.chart-column__total(v-if="item.total > 0") {{ item.total }}
                                .chart-column__bar-wrapper
                                    .chart-column__bar
                                        .chart-column__segment.chart-column__segment--missed(
                                            :style="{ height: (item.missed / maxVal * 100) + '%' }"
                                        )
                                        .chart-column__segment.chart-column__segment--success(
                                            :style="{ height: (item.success / maxVal * 100) + '%' }"
                                        )
                                span.chart-column__label {{ item.label }}

                        .stats__legend
                            .legend-item
                                span.legend-item__color.legend-item__color--success
                                span Принятые
                            .legend-item
                                span.legend-item__color.legend-item__color--missed
                                span Пропущенные

                .stats__empty(v-else)
                    p За выбранный период звонков не найдено.
</template>
<script>
import { api } from '@/api/api';
import { useUserStore } from '@/stores/user'
import dayjs from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import utc from 'dayjs/plugin/utc';
dayjs.extend(isoWeek);
dayjs.extend(utc);
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
            groupBy: 'day',
            tooltip: {
                visible: false,
                x: 0,
                y: 0,
                label: '',
                total: 0,
                success: 0,
                missed: 0
            }
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
        },
        isPhoneChoosen(){
            if(this.selectedPhones.length === 0) return false
            else return true
        },
        allCalls() {
            if (!this.history || typeof this.history !== 'object') return [];
            return Object.values(this.history).flat();
        },
        totalStats() {
            const total = this.allCalls.length;
            const success = this.allCalls.filter(c => c.status === 'success').length;
            const missed = total - success;
            return { total, success, missed };
        },
        // Агрегированные данные под график
        chartData() {
            if (!this.allCalls.length) return [];

            const groups = {};

            this.allCalls.forEach(call => {
                const localDate = new Date(call.start);
                const date = dayjs(localDate);

                let key = '';
                let label = '';

                if (this.groupBy === 'day') {
                    key = date.format('YYYY-MM-DD');
                    label = date.format('DD.MM');
                } else if (this.groupBy === 'week') {
                    const dayOfWeek = date.day(); // 0 - вс, 1 - пн ... 6 - сб
                    const diffToMonday = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
                    
                    const startOfWeek = date.add(diffToMonday, 'day');
                    const endOfWeek = startOfWeek.add(6, 'day');

                    key = startOfWeek.format('YYYY-MM-DD');
                    
                    // Красивый компактный интервал для графика: "26.10–01.11"
                    label = `${startOfWeek.format('DD.MM')}–${endOfWeek.format('DD.MM')}`;
                } else if (this.groupBy === 'month') {
                    key = date.format('YYYY-MM');
                    label = date.format('MMM YYYY');
                }

                if (!groups[key]) {
                    groups[key] = { key, label, total: 0, success: 0, missed: 0, rawDate: date };
                }

                groups[key].total++;
                if (call.status === 'success') {
                    groups[key].success++;
                } else {
                    groups[key].missed++;
                }
            });

            // Сортировка колонок хронологически слева направо
            return Object.values(groups).sort((a, b) => a.rawDate.valueOf() - b.rawDate.valueOf());
        },
        // Максимальное значение для расчета высоты колонок (минимум 1, чтобы не делить на ноль)
        maxVal() {
            const max = Math.max(...this.chartData.map(d => d.total), 1);
            return max;
        }
    },
    methods: {
        async fetchCallAnalytic(){
            this.history = null;
            if (!this.isDatesValid) return;
            if(!this.isPhoneChoosen) return;
            this.loadStatus = true;

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
        },
        showTooltip(event, item) {
            // Ищем родительскую обертку графика, относительно которой позиционируемся
            const wrapper = event.currentTarget.closest('.stats__chart-wrapper');
            if (!wrapper) return;

            const wrapperRect = wrapper.getBoundingClientRect();
            const targetRect = event.currentTarget.getBoundingClientRect();

            // Центрируем тултип по горизонтали относительно колонки
            const x = targetRect.left - wrapperRect.left + (targetRect.width / 2);
            // Размещаем над колонкой с отступом 8px
            const y = targetRect.top - wrapperRect.top - 8;

            this.tooltip = {
                visible: true,
                x,
                y,
                label: item.label,
                total: item.total,
                success: item.success,
                missed: item.missed
            };
        },

        hideTooltip() {
            this.tooltip.visible = false;
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