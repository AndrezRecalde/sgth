import { useQuery } from '@tanstack/react-query'
import { tableroSaludOcupacionalService } from '../services/tableroSaludOcupacionalService'

export function useTableroSaludOcupacional(anio: number) {
  return useQuery({
    // Bajo `solicitudes-certificacion`: iniciar, completar o cancelar una
    // solicitud ya invalida esa clave, y el tablero se entera sin recargar.
    queryKey: ['solicitudes-certificacion', 'tablero', anio],
    queryFn:  () => tableroSaludOcupacionalService.obtener(anio),
    staleTime: 1000 * 60,
  })
}
