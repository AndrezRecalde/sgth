import api from '@/lib/axios'
import type {
  ApiResponse,
  DiscapacidadCargaFamiliar,
  EnfermedadCatastroficaCargaFamiliar,
} from '@/types/api'
import type { DiscapacidadFormData } from '../schemas/discapacidad.schema'
import type { EnfermedadFormData } from '../schemas/enfermedad.schema'

/**
 * La discapacidad y la enfermedad catastrófica de una carga familiar. Las
 * marcas `persona_con_discapacidad` y `posee_enfermedad_catastrofica` del
 * familiar las deriva el backend de estos registros.
 */
const base = (cargaId: number) => `/expediente/cargas-familiares/${cargaId}`

export const condicionCargaService = {
  crearDiscapacidad: (cargaId: number, data: DiscapacidadFormData) =>
    api
      .post<ApiResponse<DiscapacidadCargaFamiliar>>(`${base(cargaId)}/discapacidades`, data)
      .then((r) => r.data.datos),

  editarDiscapacidad: (cargaId: number, id: number, data: DiscapacidadFormData) =>
    api
      .put<ApiResponse<DiscapacidadCargaFamiliar>>(`${base(cargaId)}/discapacidades/${id}`, data)
      .then((r) => r.data.datos),

  eliminarDiscapacidad: (cargaId: number, id: number) =>
    api
      .delete<ApiResponse<void>>(`${base(cargaId)}/discapacidades/${id}`)
      .then((r) => r.data),

  crearEnfermedad: (cargaId: number, data: EnfermedadFormData) =>
    api
      .post<ApiResponse<EnfermedadCatastroficaCargaFamiliar>>(`${base(cargaId)}/enfermedades`, data)
      .then((r) => r.data.datos),

  editarEnfermedad: (cargaId: number, id: number, data: EnfermedadFormData) =>
    api
      .put<ApiResponse<EnfermedadCatastroficaCargaFamiliar>>(
        `${base(cargaId)}/enfermedades/${id}`, data,
      )
      .then((r) => r.data.datos),

  eliminarEnfermedad: (cargaId: number, id: number) =>
    api
      .delete<ApiResponse<void>>(`${base(cargaId)}/enfermedades/${id}`)
      .then((r) => r.data),
}
