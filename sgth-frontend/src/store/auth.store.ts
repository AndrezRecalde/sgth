import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export interface UsuarioAuth {
  id: number;
  name: string;
  email: string;
  roles: string[];
  permisos: string[];
}

interface AuthState {
  token: string | null;
  usuario: UsuarioAuth | null;
  isAuthenticated: boolean;
  setAuth: (token: string, usuario: UsuarioAuth) => void;
  clearAuth: () => void;
  hasRole: (role: string) => boolean;
  hasPermiso: (permiso: string) => boolean;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      usuario: null,
      isAuthenticated: false,
      setAuth: (token, usuario) => {
        set({ token, usuario, isAuthenticated: true });
        if (typeof window !== 'undefined') {
          localStorage.setItem('sgth_token', token);
        }
      },
      clearAuth: () => {
        set({ token: null, usuario: null, isAuthenticated: false });
        if (typeof window !== 'undefined') {
          localStorage.removeItem('sgth_token');
        }
      },
      hasRole: (role) => get().usuario?.roles.includes(role) ?? false,
      hasPermiso: (permiso) => get().usuario?.permisos.includes(permiso) ?? false,
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({ token: state.token, usuario: state.usuario, isAuthenticated: state.isAuthenticated }),
    }
  )
);
