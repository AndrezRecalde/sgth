import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { documentoPostulanteService } from '../services/documentoPostulanteService'
import type { DocumentoPostulante } from '../services/convocatoriaService'

/**
 * Subir, bajar y quitar documentos de un postulante. Los documentos llegan con
 * el listado de postulantes, así que es esa consulta la que se refresca.
 */
export function useDocumentosPostulante(convocatoriaId: number, postulanteId: number) {
  const qc = useQueryClient()
  const refrescar = () => qc.invalidateQueries({ queryKey: ['postulantes', convocatoriaId] })

  const subir = useMutation({
    mutationFn: ({ tipo, archivo }: { tipo: string; archivo: File }) =>
      documentoPostulanteService.subir(convocatoriaId, postulanteId, tipo, archivo),
    onSuccess: () => {
      notificar.exito('Documento subido', 'El documento quedó en el expediente del candidato.')
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo subir el documento'),
  })

  const descargar = useMutation({
    mutationFn: (doc: DocumentoPostulante) =>
      documentoPostulanteService.descargar(convocatoriaId, postulanteId, doc.id)
        .then((blob) => guardarArchivo(blob, doc.nombre_archivo)),
    onError: notificar.alFallar('No se pudo descargar el documento'),
  })

  const eliminar = useMutation({
    mutationFn: (documentoId: number) =>
      documentoPostulanteService.eliminar(convocatoriaId, postulanteId, documentoId),
    onSuccess: () => {
      notificar.exito('Documento eliminado', 'El documento se quitó del candidato.')
      refrescar()
    },
    onError: notificar.alFallar('No se pudo eliminar el documento'),
  })

  return { subir, descargar, eliminar }
}
