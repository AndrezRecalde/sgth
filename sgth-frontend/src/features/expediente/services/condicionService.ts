import api from '@/lib/axios'
import type {
  ApiResponse,
  DiscapacidadServidor,
  EnfermedadCatastroficaServidor,
} from '@/types/api'
import type { DiscapacidadFormData } from '../schemas/discapacidad.schema'
import type { EnfermedadFormData } from '../schemas/enfermedad.schema'

/**
 * Discapacidades y enfermedades catastróficas del servidor.
 *
 * Van juntas porque son el mismo asunto —la condición de salud declarada— y
 * porque las marcas `tiene_discapacidad` y `tiene_enfermedad_catastrofica` de
 * la ficha se derivan de estos registros: quien toca uno invalida la otra.
 *
 * Las de una carga familiar viven en `cargaFamiliarService`, con el resto de
 * lo que cuelga de `/expediente/cargas-familiares/{id}`.
 */
export const condicionService = {
  listarDiscapacidades: (servidorId: number) =>
    api
      .get<ApiResponse<DiscapacidadServidor[]>>(
        `/expediente/servidores/${servidorId}/discapacidades`,
      )
      .then((r) => r.data.datos ?? []),

  crearDiscapacidad: (servidorId: number, data: DiscapacidadFormData) =>
    api
      .post<ApiResponse<DiscapacidadServidor>>(
        `/expediente/servidores/${servidorId}/discapacidades`, data,
      )
      .then((r) => r.data.datos),

  editarDiscapacidad: (servidorId: number, id: number, data: DiscapacidadFormData) =>
    api
      .put<ApiResponse<DiscapacidadServidor>>(
        `/expediente/servidores/${servidorId}/discapacidades/${id}`, data,
      )
      .then((r) => r.data.datos),

  eliminarDiscapacidad: (servidorId: number, id: number) =>
    api
      .delete<ApiResponse<void>>(
        `/expediente/servidores/${servidorId}/discapacidades/${id}`,
      )
      .then((r) => r.data),

  listarEnfermedades: (servidorId: number) =>
    api
      .get<ApiResponse<EnfermedadCatastroficaServidor[]>>(
        `/expediente/servidores/${servidorId}/enfermedades`,
      )
      .then((r) => r.data.datos ?? []),

  crearEnfermedad: (servidorId: number, data: EnfermedadFormData) =>
    api
      .post<ApiResponse<EnfermedadCatastroficaServidor>>(
        `/expediente/servidores/${servidorId}/enfermedades`, data,
      )
      .then((r) => r.data.datos),

  editarEnfermedad: (servidorId: number, id: number, data: EnfermedadFormData) =>
    api
      .put<ApiResponse<EnfermedadCatastroficaServidor>>(
        `/expediente/servidores/${servidorId}/enfermedades/${id}`, data,
      )
      .then((r) => r.data.datos),

  eliminarEnfermedad: (servidorId: number, id: number) =>
    api
      .delete<ApiResponse<void>>(
        `/expediente/servidores/${servidorId}/enfermedades/${id}`,
      )
      .then((r) => r.data),
}
