import api from '@/lib/axios'

export interface CertificadoEmitido {
  blob: Blob
  /** El que va impreso en el pie y lleva a la página de verificación. */
  codigo: string | null
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
}
