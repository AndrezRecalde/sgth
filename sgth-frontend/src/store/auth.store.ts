import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { borrarCookie, leerCookie } from '@/lib/cookies'

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

/**
 * Sin token: vive en la cookie HttpOnly `sgth_token`, que pone Laravel y que
 * el JavaScript de la página no puede leer. Aquí queda solo quién es la
 * persona, para pintar la interfaz.
 */
interface AuthState {
  usuario:         UsuarioAuth | null
  isAuthenticated: boolean
  setAuth:         (usuario: UsuarioAuth) => void
  clearAuth:       () => void
  hasRole:         (role: string) => boolean
  hasPermiso:      (permiso: string) => boolean
}

/**
 * Lo que dura una sesión, en días: la caducidad del token en Sanctum
 * (`sanctum.expiration`), que es la de las cookies que pone el backend. Aquí
 * solo la usa la cookie `sgth_primer_login`, que sigue escribiendo el
 * navegador.
 */
export const DURACION_SESION_DIAS = 1

/**
 * Hay sesión mientras exista `sgth_sesion`, la compañera legible de la cookie
 * del token: caducan juntas y se borran juntas.
 *
 * `sgth_token` legible es la de antes de este cambio, cuando la escribía el
 * navegador. Quien ya tenía sesión abierta al desplegar sigue dentro hasta
 * que caduque, en vez de verse expulsado a media jornada.
 */
const haySesion = () => leerCookie('sgth_sesion') !== null || leerCookie('sgth_token') !== null

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      usuario:         null,
      isAuthenticated: false,

      setAuth: (usuario) => set({ usuario, isAuthenticated: true }),

      // La cookie del token no se puede borrar desde aquí —es HttpOnly—: la
      // borran el logout del backend y `proxy.ts` al llegar a
      // `/login?logout=true`, que es adonde va siempre quien sale.
      clearAuth: () => {
        set({ usuario: null, isAuthenticated: false })
        if (typeof window !== 'undefined') {
          borrarCookie('sgth_sesion')
          borrarCookie('sgth_primer_login')
          // La de antes del cambio, que sí escribía el navegador. Sobre la
          // HttpOnly actual esto no tiene efecto.
          borrarCookie('sgth_token')
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
        usuario:         state.usuario,
        isAuthenticated: state.isAuthenticated,
      }),

      // La versión 0 guardaba el token. Al migrar se descarta y se vuelve a
      // escribir sin él, junto con la copia suelta de `sgth_token` que había
      // en localStorage: no debe quedar en el navegador ni una vez.
      version: 1,
      migrate: (guardado) => {
        if (typeof window !== 'undefined') localStorage.removeItem('sgth_token')

        const { usuario = null, isAuthenticated = false } =
          (guardado ?? {}) as Partial<Pick<AuthState, 'usuario' | 'isAuthenticated'>>

        return { usuario, isAuthenticated }
      },

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
        if (state?.isAuthenticated && !haySesion()) {
          state.clearAuth()
        }
      },
    }
  )
)
