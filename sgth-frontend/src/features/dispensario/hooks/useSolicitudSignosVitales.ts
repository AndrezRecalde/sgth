import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { solicitudCertificacionService } from '../services/solicitudCertificacionService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearSolicitudSignosVitalesData } from '../services/solicitudCertificacionService'
import { notificar } from '@/components/ui'

export function useSolicitudesPendientesTriaje() {
  return useQuery({
    queryKey: ['solicitudes-certificacion', 'pendientes-triaje'],
    queryFn:  solicitudCertificacionService.pendientesTriaje,
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
  })
}

export function useRegistrarSignosVitalesSolicitud() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({
      id, data,
    }: { id: number; data: CrearSolicitudSignosVitalesData }) =>
      solicitudCertificacionService.registrarSignosVitales(id, data),
    onSuccess: () => {
      notificar.exito(
        'Signos vitales registrados',
        'La atención SSO fue registrada correctamente.',
      )
      qc.invalidateQueries({ queryKey: ['solicitudes-certificacion'] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}
