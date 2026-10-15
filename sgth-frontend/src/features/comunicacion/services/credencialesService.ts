import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type {
  Credencial, CredencialVerificada, FiltrosCredenciales, MiCredencial, MotivoRevocacion, PedidoImpresion,
  ServidorConCredencial,
} from './tiposCredencial'

type MetaPaginada = { total: number; pagina_actual: number }

const URL = '/comunicacion'

/** Las credenciales de los servidores (`CredencialController`, fase 3.1). */
export const credencialesService = {
  listar: (filtros: FiltrosCredenciales, page: number) =>
    api.get<ApiResponse<ServidorConCredencial[], MetaPaginada>>(`${URL}/credenciales`, {
      params: { ...filtros, page, per_page: 15 },
    }).then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),

  historial: (servidorId: number) =>
    api.get<ApiResponse<Credencial[]>>(`${URL}/servidores/${servidorId}/credenciales`)
      .then((r) => r.data.datos),

  /** El PDF para imprimir. El backend registra lo impreso de cada una. */
  imprimir: (pedido: PedidoImpresion) =>
    api.post<Blob>(`${URL}/credenciales/imprimir`, pedido, { responseType: 'blob' })
      .then((r) => r.data),

  revocar: (credencialId: number, motivo: MotivoRevocacion, detalle: string | null) =>
    api.post<ApiResponse<{ revocada: Credencial; repuesta: Credencial | null }>>(
      `${URL}/credenciales/${credencialId}/revocar`,
      { motivo, detalle },
    ).then((r) => r.data),

  registrarDevolucion: (credencialId: number, fecha: string, nota: string | null) =>
    api.post<ApiResponse<Credencial>>(`${URL}/credenciales/${credencialId}/devolucion`, { fecha, nota })
      .then((r) => r.data.datos),
}

/** «Mi credencial» del Portal (`MiCredencialController`): siempre la propia. */
export const miCredencialService = {
  /** Null con el motivo en `mensaje` si todavía no la tiene. */
  ver: () =>
    api.get<ApiResponse<MiCredencial | null>>('/autoservicio/mi-credencial')
      .then((r) => ({ credencial: r.data.datos ?? null, mensaje: r.data.mensaje })),

  pdf: () =>
    api.get<Blob>('/autoservicio/mi-credencial/pdf', { responseType: 'blob' })
      .then((r) => r.data),
}

/**
 * La verificación pública, sin sesión. Lo que enseña lo decide el backend
 * (`VerificacionCredencialController`).
 */
export const verificacionCredencialService = {
  verificar: (codigo: string) =>
    api.get<ApiResponse<CredencialVerificada>>(`/credenciales/verificar/${codigo}`)
      .then((r) => r.data.datos),
}
