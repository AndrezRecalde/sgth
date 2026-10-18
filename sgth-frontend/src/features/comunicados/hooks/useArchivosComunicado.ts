import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { clavesComunicados } from '../constants/claves'
import { comunicadosService } from '../services/comunicadosService'
import type { ComunicadoAdjunto } from '../services/tipos'

/** Los PDF de un borrador; el backend rechaza tocarlos en lo publicado. */
export function useArchivosComunicado(comunicadoId: number) {
  const qc = useQueryClient()
  const refrescar = () => qc.invalidateQueries({ queryKey: clavesComunicados.todas })

  const agregarAdjunto = useMutation({
    mutationFn: (archivo: File) => comunicadosService.agregarAdjunto(comunicadoId, archivo),
    onSuccess: (adjunto) => {
      refrescar()
      notificar.exito('Documento agregado', adjunto.nombre)
    },
    onError: notificar.alFallar('No se pudo agregar el documento'),
  })

  const quitarAdjunto = useMutation({
    mutationFn: (adjunto: ComunicadoAdjunto) => comunicadosService.quitarAdjunto(comunicadoId, adjunto.id),
    onSuccess: (_, adjunto) => {
      refrescar()
      notificar.exito('Documento quitado', adjunto.nombre)
    },
    onError: notificar.alFallar('No se pudo quitar el documento'),
  })

  const descargarAdjunto = useMutation({
    mutationFn: async (adjunto: ComunicadoAdjunto) =>
      guardarArchivo(await comunicadosService.descargarAdjunto(comunicadoId, adjunto.id), adjunto.nombre),
    onError: notificar.alFallar('No se pudo descargar el documento'),
  })

  return { agregarAdjunto, quitarAdjunto, descargarAdjunto }
}
