import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import { notificar } from '@/components/ui'

export function useEquiposPorPuesto(puestoId: number | null) {
  return useQuery({
    queryKey: ['sso-puesto-epp', puestoId],
    queryFn: () => ssoService.listarEquiposPorPuesto(puestoId!),
    enabled: !!puestoId,
    staleTime: 1000 * 60 * 5,
  })
}

export function usePuestoEppMutations(puestoId: number | null) {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-puesto-epp', puestoId] })

  const asignar = useMutation({
    mutationFn: (data: { equipo_proteccion_id: number; cantidad_requerida?: number; frecuencia_reposicion_meses?: number }) =>
      ssoService.asignarEquipoAPuesto(puestoId!, data),
    onSuccess: () => {
      notificar.exito('Equipo asignado', 'El equipo de protección fue asignado al puesto.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo asignar el equipo'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarAsignacionEpp(puestoId!, id),
    onSuccess: () => {
      notificar.exito(
        'Asignación eliminada',
        'El equipo fue removido de los requerimientos del puesto.',
      )
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la asignación'),
  })

  return { asignar, eliminar }
}
