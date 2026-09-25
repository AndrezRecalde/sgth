import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { programaDrogasService } from '../services/programaDrogasService'
import { notificar } from '@/components/ui'

export function useActividadesPrograma(params?: { fase?: string; solo_activas?: boolean }) {
  return useQuery({
    queryKey: ['sso-programa-drogas-actividades', params],
    queryFn: () => programaDrogasService.listarActividades(params),
    staleTime: 1000 * 30,
  })
}

export function useListaSeguimientoPrograma(periodo: string | null) {
  return useQuery({
    queryKey: ['sso-programa-drogas-seguimiento', periodo],
    queryFn: () => programaDrogasService.listaSeguimiento(periodo!),
    enabled: !!periodo,
    staleTime: 1000 * 30,
  })
}

export function useProgramaDrogasMutations() {
  const qc = useQueryClient()

  const crearActividad = useMutation({
    mutationFn: (data: { fase: string; nombre: string; descripcion?: string }) =>
      programaDrogasService.crearActividad(data),
    onSuccess: () => {
      notificar.exito(
        'Actividad registrada',
        'La actividad fue agregada al catálogo del programa.',
      )
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-actividades'] })
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-seguimiento'] })
    },
    onError: notificar.alFallar('No se pudo registrar la actividad'),
  })

  // Retirar una actividad de la matriz sin borrar su seguimiento: es lo que la
  // guarda del backend pide cuando ya tiene registros, y hasta ahora no existía
  // en la pantalla.
  const cambiarActivoActividad = useMutation({
    mutationFn: ({ id, activo }: { id: number; activo: boolean }) =>
      programaDrogasService.actualizarActividad(id, { activo }),
    onSuccess: (_datos, { activo }) => {
      notificar.exito(
        activo ? 'Actividad reactivada' : 'Actividad desactivada',
        activo
          ? 'Vuelve a la matriz de seguimiento de los próximos períodos.'
          : 'Sale de la matriz y conserva el seguimiento ya registrado.',
      )
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-actividades'] })
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-seguimiento'] })
    },
    onError: notificar.alFallar('No se pudo cambiar el estado de la actividad'),
  })

  const eliminarActividad = useMutation({
    mutationFn: (id: number) => programaDrogasService.eliminarActividad(id),
    onSuccess: () => {
      notificar.exito('Actividad eliminada', 'La actividad fue eliminada del catálogo.')
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-actividades'] })
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-seguimiento'] })
    },
    onError: notificar.alFallar('No se pudo eliminar la actividad'),
  })

  const registrarSeguimiento = useMutation({
    mutationFn: (data: {
      programa_droga_actividad_id: number
      periodo: string
      estado: string
      fecha_ejecucion?: string | null
      observaciones?: string
    }) => programaDrogasService.registrarSeguimiento(data),
    onSuccess: () => {
      notificar.exito('Seguimiento registrado', 'El estado de la actividad fue actualizado.')
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-seguimiento'] })
    },
    onError: notificar.alFallar('No se pudo registrar el seguimiento'),
  })

  return { crearActividad, cambiarActivoActividad, eliminarActividad, registrarSeguimiento }
}
