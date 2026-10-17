import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { historialAcademicoService } from '../services/historialAcademicoService'

type Registro = { servidorId: number; id: number }

/** Los títulos y cursos de toda la institución que esperan a Talento Humano. */
export function useFormacionPorValidar() {
  return useQuery({
    queryKey: ['formacion-por-validar'],
    queryFn:  () => historialAcademicoService.porValidar(),
  })
}

/**
 * Validar o rechazar lo que el servidor registró desde el portal, y abrir el
 * PDF con el que se decide. Refresca la bandeja y el historial del servidor.
 */
export function useValidacionFormacion() {
  const qc = useQueryClient()
  const invalidar = (servidorId: number) => {
    qc.invalidateQueries({ queryKey: ['formacion-por-validar'] })
    qc.invalidateQueries({ queryKey: ['historial-academico', servidorId] })
  }

  const validar = useMutation({
    mutationFn: ({ servidorId, id }: Registro) => historialAcademicoService.validar(servidorId, id),
    onSuccess: (respuesta, { servidorId }) => {
      notificar.exito('Título validado', respuesta.mensaje)
      invalidar(servidorId)
    },
    onError: notificar.alFallar('No se pudo validar el título'),
  })

  const rechazar = useMutation({
    mutationFn: ({ servidorId, id, motivo }: Registro & { motivo: string }) =>
      historialAcademicoService.rechazar(servidorId, id, motivo),
    onSuccess: (respuesta, { servidorId }) => {
      notificar.exito('Título rechazado', respuesta.mensaje)
      invalidar(servidorId)
    },
    onError: notificar.alFallar('No se pudo rechazar el título'),
  })

  const verDocumento = useMutation({
    mutationFn: async ({ servidorId, id }: Registro) =>
      abrirOGuardarArchivo(await historialAcademicoService.documento(servidorId, id), 'titulo.pdf'),
    onError: notificar.alFallar('No se pudo abrir el documento del título'),
  })

  return { validar, rechazar, verDocumento }
}
