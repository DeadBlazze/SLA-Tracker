// Mounted
const dayjs = require('dayjs');
const todayStr = dayjs().format('YYYY-MM-DD');
const today = new Date();
process.exit();
// Корректируем timezone offset для правильного локального отображения даты
const todayStr = new Date(today.getTime() - (today.getTimezoneOffset() * 60000)).toISOString().split('T')[0];

const dateFrom = todayStr;
const dateTo = todayStr;

// fetch
const timezoneOffsetSeconds = new Date().getTimezoneOffset() * 60;
// 1. Парсим строки как жесткий UTC (добавляем суффикс Z)
const rawFromDate = new Date(`${dateFrom}T00:00:00Z`);
const rawToDate = new Date(`${dateTo}T23:59:59Z`);

// 2. Смещаем границы под локальный пояс пользователя:
const timestampFrom = Math.floor(rawFromDate.getTime() / 1000) + timezoneOffsetSeconds;
const timestampTo = Math.floor(rawToDate.getTime() / 1000) + timezoneOffsetSeconds;