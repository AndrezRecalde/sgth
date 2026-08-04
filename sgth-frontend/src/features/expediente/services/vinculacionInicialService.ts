import api from '@/lib/axios'
import type { ApiResponse, ServidorConRelaciones } from '@/types/api'
import type { VinculacionInicialFormData } from '../schemas/vinculacionInicial.schema'

/** Un vínculo de la cohorte migrada, para revisarla o auditarla. */
export type VinculoCargado = {
  id: number
  numero_contrato: string | null
  tipo_nombramiento: string | null
  fecha_inicio: string | null
  fecha_fin: string | null
  remuneracion: string | number | null
  origen: string
  servidor?: { id: number; cedula: string | null; nombre: string | null; apellido: string | null } | null
  puesto?: { cargo?: { nombre?: string | null } | null } | null
  unidad_administrativa?: { nombre?: string | null } | null
}

export const vinculacionInicialService = {
  registrar: (data: VinculacionInicialFormData) =>
    api
      .post<ApiResponse<ServidorConRelaciones>>('/expediente/vinculacion-inicial', data)
      .then((r) => r.data.datos),

  listar: () =>
    api
      .get<ApiResponse<VinculoCargado[]>>('/expediente/vinculacion-inicial')
      .then((r) => r.data.datos),
}
