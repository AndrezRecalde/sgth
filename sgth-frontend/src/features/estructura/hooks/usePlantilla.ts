import { useQuery } from '@tanstack/react-query'
import { estructuraService } from '../services/estructuraService'

/**
 * Estado de la plantilla. Cambia cuando alguien firma un contrato o se crea un
 * puesto, no cada minuto, así que se deja reposar cinco.
 */
export function usePlantilla() {
  return useQuery({
    queryKey: ['plantilla'],
    queryFn: estructuraService.plantilla,
    staleTime: 1000 * 60 * 5,
  })
}
