import api from '@/lib/axios'
import type { ApiResponse, Cargo, CargoFormData, CargoParams } from '@/types/api'

export const cargoService = {
  listar: (params?: CargoParams) =>
    api.get<ApiResponse<Cargo[]>>(
      '/estructura/cargos', { params }
    ).then(r => r.data.datos),

  crear: (data: CargoFormData) =>
    api.post<ApiResponse<Cargo>>(
      '/estructura/cargos', data
    ).then(r => r.data.datos),

  actualizar: (id: number, data: Partial<CargoFormData>) =>
    api.put<ApiResponse<Cargo>>(
      `/estructura/cargos/${id}`, data
    ).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<void>>(
      `/estructura/cargos/${id}`
    ).then(r => r.data),
}
