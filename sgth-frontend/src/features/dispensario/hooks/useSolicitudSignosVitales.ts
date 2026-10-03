import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { solicitudCertificacionService } from '../services/solicitudCertificacionService'
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
        'El médico ya puede iniciar la evaluación ocupacional.',
      )
      qc.invalidateQueries({ queryKey: ['solicitudes-certificacion'] })
      qc.invalidateQueries({ queryKey: ['dispensario', 'mi-jornada'] })
    },
    onError: notificar.alFallar('No se pudieron registrar los signos vitales'),
  })
}
