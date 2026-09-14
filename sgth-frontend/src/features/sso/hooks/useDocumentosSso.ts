import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { documentoSsoService, type TipoDocumentableSso } from '../services/documentoSsoService'
import { notificar } from '@/components/ui'

export function useDocumentosSso(tipo: TipoDocumentableSso, documentableId: number | null) {
  return useQuery({
    queryKey: ['sso-documentos', tipo, documentableId],
    queryFn: () => documentoSsoService.listar(tipo, documentableId!),
    enabled: !!documentableId,
    staleTime: 1000 * 15,
  })
}

export function useDocumentoSsoMutations(tipo: TipoDocumentableSso, documentableId: number | null) {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-documentos', tipo, documentableId] })

  const subir = useMutation({
    mutationFn: (data: { nombre: string; archivo: File }) =>
      documentoSsoService.subir({ documentable_type: tipo, documentable_id: documentableId!, ...data }),
    onSuccess: () => {
      notificar.exito('Documento subido', 'El archivo fue adjuntado exitosamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo subir el documento'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => documentoSsoService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Documento eliminado', 'El adjunto fue eliminado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el documento'),
  })

  const descargar = useMutation({
    mutationFn: (id: number) => documentoSsoService.obtenerEnlaceDescarga(id),
    onSuccess: (url) => {
      window.open(url, '_blank', 'noopener,noreferrer')
    },
    onError: notificar.alFallar('No se pudo descargar el documento'),
  })

  return { subir, eliminar, descargar }
}
