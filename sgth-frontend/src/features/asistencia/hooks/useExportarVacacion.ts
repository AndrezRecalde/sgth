import { asistenciaService } from '../services/asistenciaService'
import { useDescargaPdf } from './useDescargaPdf'

/**
 * Descargar el PDF de una solicitud de vacaciones.
 *
 * Misma mecánica que la de permisos, en `useDescargaPdf`.
 */
export function useExportarVacacion() {
  return useDescargaPdf({
    recurso:   'vacacion',
    articulo:  'la solicitud',
    descargar: (id) => asistenciaService.vacaciones.exportar(id),
  })
}
