import { useQuery } from '@tanstack/react-query'
import { contextoConsultaService } from '../services/contextoConsultaService'

export function useContextoConsulta(
  historiaClinicaId: number | null,
  agendaMedicaId?: number
) {
  return useQuery({
    queryKey: ['contexto-consulta', historiaClinicaId, agendaMedicaId],
    queryFn:  () => contextoConsultaService.obtener(
      historiaClinicaId!, agendaMedicaId
    ),
    enabled:  !!historiaClinicaId,
    staleTime: 1000 * 30,
  })
}
