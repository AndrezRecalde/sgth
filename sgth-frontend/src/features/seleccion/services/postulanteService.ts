import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { InscripcionFormData } from '../schemas/postulante.schema'
import type { Onboarding, Postulante } from './convocatoriaService'

type DatosPersonales = Omit<InscripcionFormData, 'puesto_id' | 'fecha_inscripcion'>

const base = (convocatoriaId: number, postulanteId: number) =>
  `/seleccion/convocatorias/${convocatoriaId}/postulantes/${postulanteId}`

/**
 * Lo que se hace con un candidato desde su perfil (2026-10-05): corregir sus
 * datos, eliminar una inscripción equivocada y llevar su inducción.
 */
export const postulanteService = {
  actualizar: (convocatoriaId: number, postulanteId: number, datos: DatosPersonales) =>
    api.patch<ApiResponse<Postulante>>(base(convocatoriaId, postulanteId), datos).then(r => r.data.datos),

  eliminar: (convocatoriaId: number, postulanteId: number) =>
    api.delete<ApiResponse<unknown>>(base(convocatoriaId, postulanteId)).then(r => r.data.datos),

  actualizarOnboarding: (id: number, datos: Partial<Omit<Onboarding, 'id'>>) =>
    api.patch<ApiResponse<Onboarding>>(`/seleccion/onboardings/${id}`, datos).then(r => r.data.datos),
}
