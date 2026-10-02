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

/**
 * El 401 del login no es una sesión caducada: es una contraseña mal escrita.
 *
 * Se trataba igual que cualquier otro 401, así que un intento fallido
 * recargaba la pantalla de acceso. El aviso de credenciales incorrectas
 * apenas llegaba a verse, y se perdía el `next` —el permiso del QR al que se
 * venía—, de modo que al acertar la clave se acababa en el portal.
 */
const esIntentoDeLogin = (url: string | undefined) => url === '/auth/login'

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !esIntentoDeLogin(error.config?.url)) {
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
