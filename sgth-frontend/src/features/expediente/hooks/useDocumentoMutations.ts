import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { expedienteService } from '../services/expedienteService'
import type { ApiResponse } from '@/types/api'

export function useDocumentoMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['documentos-servidor', servidorId] })
  const onError = (e: AxiosError<ApiResponse>) =>
    notifications.show({
      title: 'Error', color: 'red',
      message: e.response?.data?.mensaje ?? 'Error inesperado',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const subir = useMutation({
    mutationFn: (formData: FormData) =>
      expedienteService.subirDocumento(servidorId, formData),
    onSuccess: () => {
      notifications.show({
        title: 'Documento subido', color: 'emerald',
        message: 'El documento fue anexado al expediente.',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (documentoId: number) =>
      expedienteService.eliminarDocumento(servidorId, documentoId),
    onSuccess: () => {
      notifications.show({
        title: 'Documento eliminado', color: 'emerald',
        message: 'El documento fue eliminado.',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { subir, eliminar }
}
