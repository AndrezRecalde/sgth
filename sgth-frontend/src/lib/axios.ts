import axios from 'axios';
import { ENV } from '@/config/env';
import { useAuthStore } from '@/store/auth.store';

const api = axios.create({
  baseURL: ENV.API_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    // El token va en una cookie HttpOnly que el navegador manda solo, y el
    // backend solo la acepta con esta cabecera: un formulario de otro sitio
    // no puede ponerla. Ver TokenDesdeCookie en el backend.
    'X-Requested-With': 'XMLHttpRequest',
  },
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
