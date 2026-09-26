import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

/**
 * Lo que la página pública puede enseñar de un certificado. La cédula llega
 * enmascarada y la remuneración no llega nunca: lo decide el backend, ver
 * `VerificacionCertificadoController`.
 */
export interface CertificadoVerificado {
  codigo:          string
  documento:       string
  nombre_completo: string | null
  cedula:          string | null
  puesto:          string | null
  unidad:          string | null
  anios_servicio:  number | null
  emitido_en:      string
  vence_en:        string
  vigente:         boolean
  firmante:        string | null
  firmante_cargo:  string | null
}

export const verificacionCertificadoService = {
  verificar: (codigo: string) =>
    api
      .get<ApiResponse<CertificadoVerificado>>(`/certificados/verificar/${codigo}`)
      .then((r) => r.data.datos),
}
