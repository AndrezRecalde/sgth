import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { expedienteService } from '../services/expedienteService'
import type {} from '../schemas/servidor.schema'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'
import type { ApiResponse } from '@/types/api'
import { notificar } from '@/components/ui'

export function useServidorMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['servidores'] })
    qc.refetchQueries({ queryKey: ['servidores'] })
  }

  const onError = (error: AxiosError<ApiResponse>) => {
    notificar.error('Error', error.response?.data?.mensaje ?? 'Error inesperado')
  }

  const crear = useMutation({
    mutationFn: (data: ServidorBasicoFormData) =>
      expedienteService.crear(data),
    onSuccess: () => {
      notificar.exito('Servidor registrado', 'El expediente fue creado correctamente.')
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ServidorBasicoFormData }) =>
      expedienteService.editar(id, data),
    onSuccess: (_, { id }) => {
      notificar.exito(
        'Expediente actualizado',
        'Los datos fueron actualizados correctamente.',
      )
      invalidar()
      qc.invalidateQueries({ queryKey: ['servidor', id] })
    },
    onError,
  })

  return { crear, editar }
}
