import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface CertificadoEmitido {
  blob: Blob
  /** El que va impreso en el pie y lleva a la página de verificación. */
  codigo: string | null
}

/**
 * Una línea de la bitácora. No trae el contenido del documento: la foto del
 * expediente que cada emisión congela —con la remuneración de cada período—
 * se queda en el backend.
 */
export interface EmisionCertificado {
  id: number
  codigo: string
  tipo: string
  tipo_titulo: string
  con_remuneracion: boolean
  emitido_en: string | null
  vence_en: string | null
  vigente: boolean
  emitido_por?: string | null
  firmante_nombre: string | null
}

export const certificadoLaboralService = {
  /**
   * Emite el certificado y devuelve el PDF. Cada llamada deja constancia en
   * la bitácora del backend: no es una previsualización.
   */
  emitir: (servidorId: number, conRemuneracion: boolean) =>
    api
      .get(`/expediente/servidores/${servidorId}/certificado-laboral`, {
        params: { con_remuneracion: conRemuneracion ? 1 : 0 },
        responseType: 'blob',
      })
      .then((r): CertificadoEmitido => ({
        blob: r.data,
        // Expuesta en config/cors.php; sin eso el navegador no la deja leer.
        codigo: r.headers['x-codigo-certificado'] ?? null,
      })),

  /** Lo ya emitido para ese servidor, del más reciente al más antiguo. */
  emitidos: (servidorId: number) =>
    api
      .get<ApiResponse<EmisionCertificado[]>>(
        `/expediente/servidores/${servidorId}/certificados-emitidos`,
      )
      .then((r) => r.data.datos),
}
