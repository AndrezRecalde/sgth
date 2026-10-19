import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import type { SolicitudCargaFamiliar } from '@/types/api'
import { validacionCargasService } from '../services/validacionCargasService'

const CLAVE = 'cargas-por-validar'

/** Las solicitudes pendientes de toda la institución, o las de un servidor. */
export function useCargasPorValidar(servidorId?: number | null) {
  return useQuery({
    queryKey: [CLAVE, servidorId ?? null],
    queryFn:  () => validacionCargasService.listar(servidorId),
  })
}

/**
 * Validar o rechazar lo que el servidor pidió desde el Portal, y abrir el
 * documento con el que se decide. Validar cambia sus cargas: se refrescan la
 * bandeja y la sección del expediente.
 */
export function useValidacionCargas() {
  const qc = useQueryClient()
  const invalidar = (servidorId?: number) => {
    qc.invalidateQueries({ queryKey: [CLAVE] })
    if (servidorId) qc.invalidateQueries({ queryKey: ['cargas-familiares', servidorId] })
  }

  const validar = useMutation({
    mutationFn: (s: SolicitudCargaFamiliar) => validacionCargasService.validar(s.id),
    onSuccess: (respuesta, s) => {
      notificar.exito('Solicitud de carga validada', respuesta.mensaje)
      invalidar(s.servidor?.id)
    },
    onError: notificar.alFallar('No se pudo validar la solicitud de carga'),
  })

  const rechazar = useMutation({
    mutationFn: ({ solicitud, motivo }: { solicitud: SolicitudCargaFamiliar; motivo: string }) =>
      validacionCargasService.rechazar(solicitud.id, motivo),
    onSuccess: (respuesta, { solicitud }) => {
      notificar.exito('Solicitud de carga rechazada', respuesta.mensaje)
      invalidar(solicitud.servidor?.id)
    },
    onError: notificar.alFallar('No se pudo rechazar la solicitud de carga'),
  })

  const verDocumento = useMutation({
    mutationFn: async (s: SolicitudCargaFamiliar) => {
      if (!abrirOGuardarArchivo(await validacionCargasService.documento(s.id), s.documento_nombre ?? 'documento.pdf')) {
        notificar.aviso('El navegador bloqueó la pestaña', 'El documento se descargó.')
      }
    },
    onError: notificar.alFallar('No se pudo abrir el documento de la solicitud'),
  })

  return { validar, rechazar, verDocumento }
}
