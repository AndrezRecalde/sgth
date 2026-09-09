import { asistenciaService } from '../services/asistenciaService'
import { useDescargaPdf } from './useDescargaPdf'

/**
 * Descargar el PDF de un permiso.
 *
 * El mecanismo —blob, enlace temporal y las tres notificaciones— vive en
 * `useDescargaPdf`, que comparte con vacaciones. Aquí solo queda qué se
 * descarga y cómo se nombra en los avisos.
 */
export function useExportarPermiso() {
  return useDescargaPdf({
    recurso:   'permiso',
    articulo:  'el permiso',
    descargar: (id) => asistenciaService.permisos.exportar(id),
  })
}
