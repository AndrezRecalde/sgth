import { useQuery } from '@tanstack/react-query'
import { viaticoService } from '../services/viaticoService'
import type { ViaticoParams } from '@/types/api'

export function useViaticos(params?: ViaticoParams) {
  return useQuery({
    queryKey: ['viaticos', params],
    queryFn:  () => viaticoService.listar(params),
    staleTime: 0,
  })
}

export function useViatico(id: number | null) {
  return useQuery({
    queryKey: ['viatico', id],
    queryFn:  () => viaticoService.obtener(id!),
    enabled:  !!id,
    staleTime: 0,
  })
}

export function useTramos(viaticoId: number | null) {
  return useQuery({
    queryKey: ['tramos', viaticoId],
    queryFn:  () => viaticoService.tramos.listar(viaticoId!),
    enabled:  !!viaticoId,
    staleTime: 0,
  })
}

export function useTiposTransporte() {
  return useQuery({
    queryKey: ['tipos-transporte'],
    queryFn:  viaticoService.catalogos.tiposTransporte,
    staleTime: 1000 * 60 * 60, // 1 hora
  })
}

export function useEmpresasPorTipo(tipoId: number | null) {
  return useQuery({
    queryKey: ['empresas-transporte', tipoId],
    queryFn:  () =>
      viaticoService.catalogos.empresasPorTipo(tipoId!),
    enabled:  !!tipoId,
    staleTime: 1000 * 60 * 60,
  })
}

export function useCategoriasFactura() {
  return useQuery({
    queryKey: ['categorias-factura'],
    queryFn:  viaticoService.catalogos.categoriasFactura,
    staleTime: 1000 * 60 * 60,
  })
}

export function useVuelosAutorizacion() {
  return useQuery({
    queryKey: ['vuelos-autorizacion'],
    queryFn:  viaticoService.vuelos.listar,
    staleTime: 0,
  })
}
