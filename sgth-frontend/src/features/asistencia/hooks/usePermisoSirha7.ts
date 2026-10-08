import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { permisoSirha7Service } from '../services/permisoSirha7Service'

/**
 * Aprobar un permiso registrándolo en Sirha7, el biométrico.
 *
 * Los tipos cambian casi nunca (los mantiene TH en Sirha7): se reusan unos
 * minutos. La previa sí se pide cada vez que se abre el diálogo, porque los
 * cruces dependen de los otros permisos de la persona en ese momento.
 */
export function useTiposSirha7(activo: boolean) {
  return useQuery({
    queryKey:  ['sirha7-tipos'],
    queryFn:   permisoSirha7Service.tipos,
    enabled:   activo,
    staleTime: 1000 * 60 * 10,
  })
}

export function usePreviaSirha7(permisoId: number | null) {
  return useQuery({
    queryKey: ['sirha7-previa', permisoId],
    queryFn:  () => permisoSirha7Service.previa(permisoId ?? 0),
    enabled:  permisoId !== null,
    staleTime: 0,
  })
}

export function useAprobarSirha7() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, leaveId }: { id: number; leaveId: number }) =>
      permisoSirha7Service.aprobar(id, leaveId),
    onSuccess: (permiso) => {
      const omitidos = permiso.sirha7_dias_omitidos?.length ?? 0

      notificar.exito(
        'Permiso registrado en Sirha7',
        omitidos
          ? `Quedó como ${permiso.sirha7_leave_nombre}. Se omitieron ${omitidos} día(s) ya cubiertos o sin jornada.`
          : `Quedó como ${permiso.sirha7_leave_nombre}.`,
      )
      // El mismo permiso se ve en la tabla, en el portal y en la ficha del QR.
      qc.invalidateQueries({ queryKey: ['permisos'] })
      qc.invalidateQueries({ queryKey: ['mis-permisos'] })
      qc.invalidateQueries({ queryKey: ['permiso-por-folio'] })
      qc.invalidateQueries({ queryKey: ['sirha7-previa'] })
    },
    onError: notificar.alFallar('No se pudo registrar el permiso en Sirha7'),
  })
}
