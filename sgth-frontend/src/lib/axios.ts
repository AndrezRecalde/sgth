import axios from 'axios';
import { ENV } from '@/config/env';
import { useAuthStore } from '@/store/auth.store';

const api = axios.create({
  baseURL: ENV.API_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = typeof window !== 'undefined' ? localStorage.getItem('sgth_token') : null;
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      if (typeof window !== 'undefined') {
        useAuthStore.getState().clearAuth();
        if (window.location.pathname !== '/login' || !window.location.search.includes('logout=true')) {
          window.location.href = '/login?logout=true';
        }
      }
    }
    return Promise.reject(error);
  }
);

export default api;
