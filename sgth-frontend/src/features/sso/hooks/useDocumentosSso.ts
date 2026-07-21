import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { documentoSsoService, type TipoDocumentableSso } from '../services/documentoSsoService'
import { getApiErrorMessage } from '@/types/api'

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
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-documentos', tipo, documentableId] })

  const subir = useMutation({
    mutationFn: (data: { nombre: string; archivo: File }) =>
      documentoSsoService.subir({ documentable_type: tipo, documentable_id: documentableId!, ...data }),
    onSuccess: () => {
      notifications.show({
        title: 'Documento subido',
        message: 'El archivo fue adjuntado exitosamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => documentoSsoService.eliminar(id),
    onSuccess: () => {
      notifications.show({
        title: 'Documento eliminado',
        message: 'El adjunto fue eliminado.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
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
