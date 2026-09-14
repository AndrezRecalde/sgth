import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { documentoSsoService, type TipoDocumentableSso } from '../services/documentoSsoService'
import { getApiErrorMessage } from '@/types/api'
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

  const onError = (error: unknown) =>
    notificar.error('Error', getApiErrorMessage(error))

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-documentos', tipo, documentableId] })

  const subir = useMutation({
    mutationFn: (data: { nombre: string; archivo: File }) =>
      documentoSsoService.subir({ documentable_type: tipo, documentable_id: documentableId!, ...data }),
    onSuccess: () => {
      notificar.exito('Documento subido', 'El archivo fue adjuntado exitosamente.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => documentoSsoService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Documento eliminado', 'El adjunto fue eliminado.')
      invalidar()
    },
    onError,
  })

  const descargar = useMutation({
    mutationFn: (id: number) => documentoSsoService.obtenerEnlaceDescarga(id),
    onSuccess: (url) => {
      window.open(url, '_blank', 'noopener,noreferrer')
    },
    onError,
  })

  return { subir, eliminar, descargar }
}
