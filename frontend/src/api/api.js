import axios from 'axios'
import router from '@/router'; // импортируйте ваш настроенный роутер

export const api = axios.create({
  baseURL: import.meta.env.VITE_API
})

// Добавляем интерцептор ответов
api.interceptors.response.use(
  (response) => {
    // Если статус ответа 2xx, просто возвращаем данные
    return response;
  },
  (error) => {
    // Если сервер вернул ошибку
    if (error.response) {
      const status = error.response.status;

      if (status === 401) {
        // Неавторизован -> перенаправляем на логин
        router.push('/login');
      } else if (status === 403) {
        // Нет доступа -> перенаправляем на forbidden
        router.push('/forbidden');
      }
    }
    
    // Пробрасываем ошибку дальше, если нужно обработать её где-то ещё
    return Promise.reject(error);
  }
)