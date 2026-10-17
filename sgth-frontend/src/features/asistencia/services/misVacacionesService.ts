import api from '@/lib/axios'
import type { ApiResponse, MisVacaciones } from '@/types/api'

/**
 * Las vacaciones del servidor con sesión, desde el autoservicio.
 *
 * No usa `/asistencia/vacaciones`: ese listado es el de Talento Humano y los
 * jefes (`VacacionPolicy::verAny`) y al servidor le responde 403. Aquí el
 * backend sabe de quién son por la sesión.
 */
export const misVacacionesService = {
  listar: () =>
    api.get<ApiResponse<MisVacaciones>>('/autoservicio/mis-vacaciones')
      .then(r => r.data.datos),
}
