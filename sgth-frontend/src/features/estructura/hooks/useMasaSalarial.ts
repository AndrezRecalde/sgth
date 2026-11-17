import { keepPreviousData, useQuery } from '@tanstack/react-query'
import type { MasaSalarialParams } from '@/types/api'
import { masaSalarialService } from '../services/masaSalarialService'

/**
 * La masa salarial a una fecha de corte. Cambia cuando se registra un acto o
 * un contrato, no cada minuto. Al cambiar un filtro se queda lo anterior en
 * pantalla mientras llega lo nuevo, para que la página no salte.
 */
export function useMasaSalarial(params: MasaSalarialParams) {
  return useQuery({
    queryKey: ['masa-salarial', params],
    queryFn: () => masaSalarialService.resumen(params),
    staleTime: 1000 * 60 * 5,
    placeholderData: keepPreviousData,
  })
}
