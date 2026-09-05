import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

/**
 * Lo que el médico lleva escrito de una consulta que aún no ha guardado.
 *
 * Vive en el servidor y no en el navegador: los campos clínicos se guardan
 * cifrados, y dejar ese mismo texto en claro en el equipo del consultorio
 * —compartido— sería deshacer esa decisión.
 */
export interface BorradorConsulta {
  id:               number
  agenda_medica_id: number
  medico_id:        number
  contenido:        Record<string, unknown>
  updated_at:       string
}

const RUTA = '/dispensario/consultas/borrador'

export const borradorService = {
  obtener: (agendaMedicaId: number) =>
    api.get<ApiResponse<BorradorConsulta | null>>(RUTA, {
      params: { agenda_medica_id: agendaMedicaId },
    }).then(r => r.data.datos),

  guardar: (agendaMedicaId: number, contenido: Record<string, unknown>) =>
    api.put<ApiResponse<BorradorConsulta>>(RUTA, {
      agenda_medica_id: agendaMedicaId,
      contenido,
    }).then(r => r.data.datos),

  descartar: (agendaMedicaId: number) =>
    api.delete<ApiResponse<null>>(RUTA, {
      data: { agenda_medica_id: agendaMedicaId },
    }).then(r => r.data.datos),
}
