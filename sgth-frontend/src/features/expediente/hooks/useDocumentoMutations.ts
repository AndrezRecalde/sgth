import { useMutation, useQueryClient } from '@tanstack/react-query'
import { documentoService } from '../services/documentoService'
import { notificar } from '@/components/ui'

export function useDocumentoMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['documentos-servidor', servidorId] })

  const subir = useMutation({
    mutationFn: (formData: FormData) =>
      documentoService.subir(servidorId, formData),
    onSuccess: () => {
      notificar.exito('Documento subido', 'El documento fue anexado al expediente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo subir el documento'),
  })

  const eliminar = useMutation({
    mutationFn: (documentoId: number) =>
      documentoService.eliminar(servidorId, documentoId),
    onSuccess: () => {
      notificar.exito('Documento eliminado', 'El documento fue eliminado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el documento'),
  })

  return { subir, eliminar }
}
