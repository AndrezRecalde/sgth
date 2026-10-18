import { useAuthStore } from '@/store/auth.store'

const SIN_PERMISOS: readonly string[] = []

/**
 * Qué puede hacer quien mira (docs/diseno-noticias-comunicados.md, 7.3).
 * Publicar lo tiene admin-uath por su rol, y un asistente solo si se le dio
 * como permiso adicional desde Usuarios (D1): la sesión trae los dos juntos.
 *
 * Se suscribe a los permisos y no a `hasPermiso`, que es siempre la misma
 * función: con ella no se volvía a pintar al rehidratarse la sesión.
 */
export function usePermisosComunicados() {
  const permisos = useAuthStore((s) => s.usuario?.permisos ?? SIN_PERMISOS)

  return {
    redacta: permisos.includes('redactar-comunicados'),
    publica: permisos.includes('publicar-comunicados'),
    veLecturas: permisos.includes('ver-lecturas-comunicados'),
  }
}
