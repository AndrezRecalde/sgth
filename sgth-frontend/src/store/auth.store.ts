import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export interface UsuarioAuth {
  id:               number
  nombre_completo?: string
  email:            string
  usuario_ti?:      string
  servidor_id?:     number | null
  activo?:          boolean
  servidor?: {
    id:                 number
    cedula?:            string
    nombre?:            string
    apellido?:          string
    activo?:            boolean
    puede_marcar?:      boolean
    regimen_laboral?:   string
    tipo_nombramiento?: string | null
    tipo_nombramiento_label?: string | null
    unidad_administrativa_id?: number | null
    unidad_administrativa?: {
      id:      number
      nombre?: string
    } | null
    puesto?: {
      id:      number
      nombre?: string
      es_jefe?: boolean
    } | null
    contrato_vigente?: {
      id:      number
      estado?: string
    } | null
  } | null
  roles:    string[]
  permisos: string[]
}

interface AuthState {
  token:           string | null
  usuario:         UsuarioAuth | null
  isAuthenticated: boolean
  setAuth:         (token: string, usuario: UsuarioAuth) => void
  clearAuth:       () => void
  hasRole:         (role: string) => boolean
  hasPermiso:      (permiso: string) => boolean
}

const setCookie = (name: string, value: string, days = 1) => {
  if (typeof document === 'undefined') return
  const expires = new Date(Date.now() + days * 864e5).toUTCString()
  document.cookie = `${name}=${value}; expires=${expires}; path=/`
}

const deleteCookie = (name: string) => {
  if (typeof document === 'undefined') return
  document.cookie = `${name}=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; max-age=0;`
  document.cookie = `${name}=; path=/; domain=${window.location.hostname}; expires=Thu, 01 Jan 1970 00:00:00 GMT; max-age=0;`
  document.cookie = `${name}=; path=/; domain=.${window.location.hostname}; expires=Thu, 01 Jan 1970 00:00:00 GMT; max-age=0;`
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token:           null,
      usuario:         null,
      isAuthenticated: false,

      setAuth: (token, usuario) => {
        set({ token, usuario, isAuthenticated: true })
        if (typeof window !== 'undefined') {
          localStorage.setItem('sgth_token', token)
          setCookie('sgth_token', token)
        }
      },

      clearAuth: () => {
        set({ token: null, usuario: null, isAuthenticated: false })
        if (typeof window !== 'undefined') {
          localStorage.removeItem('sgth_token')
          deleteCookie('sgth_token')
          deleteCookie('sgth_primer_login')
        }
      },

      hasRole:    (role)    => get().usuario?.roles.includes(role)      ?? false,
      hasPermiso: (permiso) => get().usuario?.permisos.includes(permiso) ?? false,
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({
        token:           state.token,
        usuario:         state.usuario,
        isAuthenticated: state.isAuthenticated,
      }),
    }
  )
)
