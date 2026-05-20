import { useAuthStore } from '../store/auth.store'

export function useAuth() {
  const store = useAuthStore()
  
  return {
    token: store.token,
    usuario: store.usuario,
    isAuthenticated: store.isAuthenticated,
    setAuth: store.setAuth,
    clearAuth: store.clearAuth,
    hasRole: store.hasRole,
    hasPermiso: store.hasPermiso,
  }
}
