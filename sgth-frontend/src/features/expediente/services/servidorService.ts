import api from '@/lib/axios'
import type {
  ApiResponse,
  Servidor,
  ServidorConRelaciones,
  ServidorParams,
} from '@/types/api'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'
import type { ServidorLaboralFormData } from '../schemas/servidorLaboral.schema'

export const servidorService = {
  listar: (params?: ServidorParams) =>
    api
      .get<{
        exito: boolean
        datos: ServidorConRelaciones[]
        meta: {
          pagina_actual: number
          por_pagina: number
          total: number
          ultima_pagina: number
        }
      }>('/expediente/servidores', { params })
      .then((r) => ({
        data: r.data.datos ?? [],
        total: r.data.meta?.total ?? 0,
        current_page: r.data.meta?.pagina_actual ?? 1,
      })),

  // `ServidorController` responde con `obtenerExpedienteCompleto`:
  // trae unidad, puesto con cargo y partida, y contrato vigente.
  obtener: (id: number) =>
    api
      .get<ApiResponse<ServidorConRelaciones>>(`/expediente/servidores/${id}`)
      .then((r) => r.data.datos),

  // `storeBasico` responde con un `ServidorResource` recién creado: sin
  // relaciones cargadas, pero con la ficha completa que el llamador necesita
  // para encadenar la vinculación.
  crear: (data: ServidorBasicoFormData) =>
    api
      .post<ApiResponse<ServidorConRelaciones>>('/expediente/servidores/basico', data)
      .then((r) => r.data.datos),

  editar: (
    id: number,
    data: Partial<ServidorBasicoFormData & ServidorLaboralFormData>,
  ) =>
    api
      .put<ApiResponse<Servidor>>(`/expediente/servidores/${id}`, data)
      .then((r) => r.data.datos),

  exportarExcel: (params?: ServidorParams) =>
    api
      .get('/expediente/servidores-export/excel', { params, responseType: 'blob' })
      .then((r) => r.data),

  exportarPdf: (params?: ServidorParams) =>
    api
      .get('/expediente/servidores-export/pdf', { params, responseType: 'blob' })
      .then((r) => r.data),
}
