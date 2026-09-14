import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { expedienteService } from '../services/expedienteService'
import type { ApiResponse } from '@/types/api'
import { notificar } from '@/components/ui'

export function useHistorialAcademicoMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['historial-academico', servidorId] })
  const onError = (e: AxiosError<ApiResponse>) =>
    notificar.error('Error', e.response?.data?.mensaje ?? 'Error inesperado')

  const crear = useMutation({
    mutationFn: (data: Parameters<typeof expedienteService.crearHistorialAcademico>[1]) =>
      expedienteService.crearHistorialAcademico(servidorId, data),
    onSuccess: () => {
      notificar.exito('Título registrado', 'El título académico fue registrado.')
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Parameters<typeof expedienteService.editarHistorialAcademico>[2] }) =>
      expedienteService.editarHistorialAcademico(servidorId, id, data),
    onSuccess: () => {
      notificar.exito('Título actualizado', 'El título académico fue actualizado.')
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      expedienteService.eliminarHistorialAcademico(servidorId, id),
    onSuccess: () => {
      notificar.exito('Registro eliminado', 'El registro académico fue eliminado.')
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
