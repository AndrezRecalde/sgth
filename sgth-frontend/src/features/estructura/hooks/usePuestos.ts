import { useQuery } from '@tanstack/react-query'
import type { PuestoParams } from '@/types/api'
import { puestosExtensionesService } from '../services/puestosExtensionesService'

/**
 * `habilitado` para las pantallas que solo piden puestos después de elegir algo
 * —una unidad, normalmente—. Sin él, pasar `undefined` como parámetros no
 * cancela la consulta: pide la primera página de TODOS los puestos para
 * alimentar un selector que todavía está deshabilitado.
 */
export function usePuestos(params?: PuestoParams, opciones?: { habilitado?: boolean }) {
  return useQuery({
    queryKey: ['puestos', params],
    queryFn: () => puestosExtensionesService.listarPuestos(params),
    staleTime: 1000 * 60 * 5,
    enabled: opciones?.habilitado ?? true,
  })
}
