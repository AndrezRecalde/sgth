import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { solicitudesAccionService } from '../services/solicitudesAccionService'
import type { EstadoSolicitudAccion } from '@/features/portal/services/misSolicitudesService'

/** Las solicitudes del portal que atiende Talento Humano (fase 6.3b). */
export function useSolicitudesAccion(estado?: EstadoSolicitudAccion | null) {
  return useQuery({
    queryKey: ['solicitudes-accion', estado ?? null],
    queryFn: () => solicitudesAccionService.listar(estado),
    staleTime: 1000 * 30,
  })
}

/** Preparar la acción deja un borrador en la bandeja: se refrescan las dos. */
export function usePrepararSolicitud() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => solicitudesAccionService.preparar(id),
    onSuccess: (s) => {
      notificar.exito('Acción preparada', `El borrador que atiende ${s.folio} está en la bandeja de acciones.`)
      qc.invalidateQueries({ queryKey: ['solicitudes-accion'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
    },
    onError: notificar.alFallar('No se pudo preparar la acción'),
  })
}

export function useRechazarSolicitud() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => solicitudesAccionService.rechazar(id, motivo),
    onSuccess: (s) => {
      notificar.exito('Solicitud rechazada', `${s.folio}: el servidor ve el motivo en su portal.`)
      qc.invalidateQueries({ queryKey: ['solicitudes-accion'] })
    },
    onError: notificar.alFallar('No se pudo rechazar la solicitud'),
  })
}
