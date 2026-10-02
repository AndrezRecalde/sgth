import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { borrarCookie, escribirCookie, leerCookie } from '@/lib/cookies'

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

/**
 * Lo que dura una sesión, en días.
 *
 * Es la caducidad de la cookie `sgth_token`, y con ella la de la sesión
 * entera: `proxy.ts` decide con la cookie, así que cuando expira ya no se
 * puede abrir ninguna pantalla.
 */
export const DURACION_SESION_DIAS = 1

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
          escribirCookie('sgth_token', token, DURACION_SESION_DIAS)
        }
      },

      clearAuth: () => {
        set({ token: null, usuario: null, isAuthenticated: false })
        if (typeof window !== 'undefined') {
          localStorage.removeItem('sgth_token')
          borrarCookie('sgth_token')
          borrarCookie('sgth_primer_login')
        }
      },

      // `?.` también sobre la lista: las sesiones guardadas antes de que el
      // login devolviera roles y permisos los tienen sin definir, y la pantalla
      // se caía con «Cannot read properties of undefined (reading 'includes')».
      hasRole:    (role)    => get().usuario?.roles?.includes(role)      ?? false,
      hasPermiso: (permiso) => get().usuario?.permisos?.includes(permiso) ?? false,
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({
        token:           state.token,
        usuario:         state.usuario,
        isAuthenticated: state.isAuthenticated,
      }),

      /**
       * La cookie manda: si caducó, la sesión guardada tampoco vale.
       *
       * `localStorage` no caduca, así que el store seguía afirmando que había
       * sesión mucho después de que la cookie —lo único que mira `proxy.ts`—
       * hubiera expirado. La persona veía el sistema como si estuviera dentro
       * y era devuelta al login en la primera navegación, sin explicación.
       *
       * En vez de guardar aquí una segunda fecha de caducidad que se puede
       * desincronizar de la cookie, se lee la cookie: el reloj es uno solo.
       * Sin ella, esto limpia la sesión y `SGTHAppShell` lleva al login.
       */
      onRehydrateStorage: () => (state) => {
        if (state?.isAuthenticated && !leerCookie('sgth_token')) {
          state.clearAuth()
        }
      },
    }
  )
)
