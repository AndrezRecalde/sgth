import api from '@/lib/axios'
import type {
  ApiResponse,
  CuentaBancariaServidor,
  EntidadFinanciera,
} from '@/types/api'
import type { CuentaBancariaFormData } from '../schemas/cuentaBancaria.schema'

export const cuentaBancariaService = {
  listar: (servidorId: number) =>
    api.get<ApiResponse<CuentaBancariaServidor[]>>(
      `/expediente/servidores/${servidorId}/cuentas-bancarias`
    ).then(r => r.data.datos ?? []),

  crear: (servidorId: number, data: CuentaBancariaFormData) =>
    api.post<ApiResponse<CuentaBancariaServidor>>(
      `/expediente/servidores/${servidorId}/cuentas-bancarias`, data
    ).then(r => r.data.datos),

  editar: (servidorId: number, id: number, data: CuentaBancariaFormData) =>
    api.put<ApiResponse<CuentaBancariaServidor>>(
      `/expediente/servidores/${servidorId}/cuentas-bancarias/${id}`, data
    ).then(r => r.data.datos),

  setPrincipal: (servidorId: number, id: number, proposito: 'sueldo' | 'viatico') =>
    api.post<ApiResponse<void>>(
      `/expediente/servidores/${servidorId}/cuentas-bancarias/${id}/set-principal`,
      { proposito }
    ).then(r => r.data),

  eliminar: (servidorId: number, id: number) =>
    api.delete<ApiResponse<void>>(
      `/expediente/servidores/${servidorId}/cuentas-bancarias/${id}`
    ).then(r => r.data),

  entidadesFinancieras: () =>
    api.get<ApiResponse<EntidadFinanciera[]>>(
      '/catalogos/entidades-financieras'
    ).then(r => r.data.datos ?? []),
}
