import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import { clavesSso } from '../constants/claves'
import { notificar } from '@/components/ui'

export function useEquiposPorPuesto(puestoId: number | null) {
  return useQuery({
    queryKey: clavesSso.epp.porPuesto(puestoId),
    queryFn: () => ssoService.listarEquiposPorPuesto(puestoId!),
    enabled: !!puestoId,
    staleTime: 1000 * 60 * 5,
  })
}

export function usePuestoEppMutations(puestoId: number | null) {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesSso.epp.porPuesto(puestoId) })
    // El kit de un servidor es el EPP requerido de su puesto menos lo que ya
    // recibió: al cambiar el requerimiento hay que rehacerlo. No se puede
    // acotar a los servidores de este puesto —la caché no sabe de qué puesto
    // es cada kit—, así que se invalidan todos.
    qc.invalidateQueries({ queryKey: clavesSso.epp.kits })
    // Y la cobertura de EPP cuenta los puestos con EPP requerido.
    qc.invalidateQueries({ queryKey: clavesSso.indicadores.todos })
    qc.invalidateQueries({ queryKey: clavesSso.tablero.todo })
  }

  const asignar = useMutation({
    mutationFn: (data: { equipo_proteccion_id: number; cantidad_requerida?: number; frecuencia_reposicion_meses?: number }) =>
      ssoService.asignarEquipoAPuesto(puestoId!, data),
    onSuccess: () => {
      notificar.exito('Equipo asignado', 'El equipo de protección fue asignado al puesto.')
      invalidar()
    },
    // El formulario reparte el 422 por campo; notificarlo además lo repetía.
    onError: notificar.alFallarSalvoCampos('No se pudo asignar el equipo'),
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
