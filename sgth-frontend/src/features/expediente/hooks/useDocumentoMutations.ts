import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { expedienteService } from '../services/expedienteService'
import type { ApiResponse } from '@/types/api'
import { notificar } from '@/components/ui'

export function useDocumentoMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['documentos-servidor', servidorId] })
  const onError = (e: AxiosError<ApiResponse>) =>
    notificar.error('Error', e.response?.data?.mensaje ?? 'Error inesperado')

  const subir = useMutation({
    mutationFn: (formData: FormData) =>
      expedienteService.subirDocumento(servidorId, formData),
    onSuccess: () => {
      notificar.exito('Documento subido', 'El documento fue anexado al expediente.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (documentoId: number) =>
      expedienteService.eliminarDocumento(servidorId, documentoId),
    onSuccess: () => {
      notificar.exito('Documento eliminado', 'El documento fue eliminado.')
      invalidar()
    },
    onError,
  })

  return { subir, eliminar }
}
