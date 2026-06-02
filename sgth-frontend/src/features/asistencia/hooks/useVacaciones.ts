import { useQuery } from '@tanstack/react-query'
import { asistenciaService } from '../services/asistenciaService'

interface FiltrosVacacion {
  folio?:                    string
  estado?:                   string
  motivo?:                   string
  servidor_id?:              number
  unidad_administrativa_id?: number
  fecha_desde?:              string
  fecha_hasta?:              string
  per_page?:                 number
}

export function useVacaciones(filtros?: FiltrosVacacion) {
  return useQuery({
    queryKey: ['vacaciones', filtros],
    queryFn:  () => asistenciaService.vacaciones.listar(filtros),
    staleTime: 0,
  })
}
