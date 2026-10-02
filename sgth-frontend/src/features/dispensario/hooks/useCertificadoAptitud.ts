import { useState } from 'react'
import { notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { getApiErrorMessage } from '@/types/api'
import { solicitudCertificacionService } from '../services/solicitudCertificacionService'

/**
 * Descarga el certificado de aptitud de una evaluación.
 *
 * `descargandoId` dice QUÉ fila lo pidió, y no solo que algo se está
 * descargando: con un `loading` suelto giraban a la vez todos los botones de
 * la tabla, que es el defecto que tenía el botón del FEMO.
 */
export function useCertificadoAptitud() {
  const [descargandoId, setDescargandoId] = useState<number | null>(null)

  const descargar = async (solicitudId: number, cedula: string) => {
    setDescargandoId(solicitudId)
    try {
      guardarArchivo(
        await solicitudCertificacionService.descargarCertificadoAptitud(solicitudId),
        `certificado-aptitud-${cedula}.pdf`,
      )
    } catch (e) {
      notificar.error(
        'No se pudo generar el certificado de aptitud',
        getApiErrorMessage(e, 'Inténtalo de nuevo en unos segundos.'),
      )
    } finally {
      setDescargandoId(null)
    }
  }

  return { descargar, descargandoId }
}
