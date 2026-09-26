import { useMutation, useQueryClient } from '@tanstack/react-query'
import { historialAcademicoService } from '../services/historialAcademicoService'
import { notificar } from '@/components/ui'

export function useHistorialAcademicoMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['historial-academico', servidorId] })

  const crear = useMutation({
    mutationFn: (data: Parameters<typeof historialAcademicoService.crear>[1]) =>
      historialAcademicoService.crear(servidorId, data),
    onSuccess: () => {
      notificar.exito('Título registrado', 'El título académico fue registrado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar el título'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Parameters<typeof historialAcademicoService.editar>[2] }) =>
      historialAcademicoService.editar(servidorId, id, data),
    onSuccess: () => {
      notificar.exito('Título actualizado', 'El título académico fue actualizado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar el título'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      historialAcademicoService.eliminar(servidorId, id),
    onSuccess: () => {
      notificar.exito('Registro eliminado', 'El registro académico fue eliminado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el título'),
  })

  return { crear, editar, eliminar }
}
