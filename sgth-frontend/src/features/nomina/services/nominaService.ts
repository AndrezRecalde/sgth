import api from '@/lib/axios'
import type { ApiResponse, Nomina, RolPago, DescuentoRecurrente, ConceptoNomina } from '@/types/api'

export const nominaService = {
  listar: () =>
    api.get<ApiResponse<Nomina[]>>('/nomina')
      .then(r => r.data.datos ?? []),

  obtener: (id: number) =>
    api.get<ApiResponse<Nomina>>(`/nomina/${id}`)
      .then(r => r.data.datos),

  calcular: (periodo: string) =>
    api.post<ApiResponse<Nomina>>('/nomina', { periodo })
      .then(r => r.data.datos),

  cerrar: (id: number) =>
    api.post<ApiResponse<Nomina>>(`/nomina/${id}/cerrar`)
      .then(r => r.data.datos),

  rolPago: (nominaId: number, servidorId: number) =>
    api.get<ApiResponse<RolPago>>(
      `/nomina/${nominaId}/rol-pago/${servidorId}`
    ).then(r => r.data.datos),

  conceptos: () =>
    api.get<ApiResponse<ConceptoNomina[]>>('/nomina/conceptos')
      .then(r => r.data.datos ?? []),

  descuentos: {
    listar: () =>
      api.get<ApiResponse<DescuentoRecurrente[]>>(
        '/nomina/descuentos-recurrentes'
      ).then(r => r.data.datos ?? []),

    crear: (data: Record<string, unknown>) =>
      api.post<ApiResponse<DescuentoRecurrente>>(
        '/nomina/descuentos-recurrentes', data
      ).then(r => r.data.datos),

    editar: (id: number, data: Record<string, unknown>) =>
      api.put<ApiResponse<DescuentoRecurrente>>(
        `/nomina/descuentos-recurrentes/${id}`, data
      ).then(r => r.data.datos),

    eliminar: (id: number) =>
      api.delete<ApiResponse<void>>(
        `/nomina/descuentos-recurrentes/${id}`
      ).then(r => r.data),
  },
}
