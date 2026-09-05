import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { inventarioMedicinaService } from '../services/inventarioMedicinaService'
import { getApiErrorMessage } from '@/types/api'
import type {
  CrearMedicinaData, ActualizarMedicinaData,
} from '../services/inventarioMedicinaService'

export function useInventarioMedicinas(
  params?: Record<string, unknown>
) {
  return useQuery({
    queryKey: ['inventario-medicinas', params],
    queryFn:  () => inventarioMedicinaService.listar(params),
    staleTime: 1000 * 30,
  })
}

/**
 * Los lotes con existencias de una medicina, en orden FEFO.
 *
 * Se piden al abrir el diálogo de baja: el listado no los trae, y elegir de
 * qué lote sale una baja necesita verlos con su caducidad y sus unidades.
 */
export function useLotesDeMedicina(id: number | null, enabled = true) {
  return useQuery({
    queryKey: ['inventario-medicinas', 'lotes', id],
    queryFn:  () => inventarioMedicinaService.obtener(id!)
      .then(m => m.lotes ?? []),
    enabled:  !!id && enabled,
    staleTime: 0,
  })
}

export function useKardexMedicina(id: number | null, page = 1) {
  return useQuery({
    queryKey: ['inventario-medicinas', 'kardex', id, page],
    queryFn:  () => inventarioMedicinaService.kardex(id!, page),
    enabled:  !!id,
    // La página anterior se queda a la vista mientras llega la siguiente.
    placeholderData: (anterior) => anterior,
  })
}

/**
 * `enabled` lo usa el menú lateral, que vive en los tres subsistemas: solo
 * tiene sentido pedir el conteo a quien puede entrar al inventario.
 */
export function useStockBajoCount({ enabled = true }: { enabled?: boolean } = {}) {
  return useQuery({
    queryKey: ['inventario-medicinas', 'stock-bajo-count'],
    queryFn:  () => inventarioMedicinaService.contarStockBajo(),
    enabled,
    staleTime: 1000 * 60,
    refetchInterval: 1000 * 60 * 5,
  })
}

export function useInventarioMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['inventario-medicinas'] })

  const onError = (error: unknown) =>
    notifications.show({
      title:   'Error',
      message: getApiErrorMessage(error),
      color:   'red',
      icon:    React.createElement(IconX, { size: 16 }),
    })

  const crear = useMutation({
    mutationFn: (data: CrearMedicinaData) =>
      inventarioMedicinaService.crear(data),
    onSuccess: () => {
      notifications.show({
        title:   'Medicina registrada',
        message: 'La medicina fue agregada al inventario.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const actualizar = useMutation({
    mutationFn: ({ id, data }: {
      id: number; data: ActualizarMedicinaData
    }) => inventarioMedicinaService.actualizar(id, data),
    onSuccess: () => {
      notifications.show({
        title:   'Medicina actualizada',
        message: 'Los datos fueron actualizados.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const registrarBaja = useMutation({
    mutationFn: ({ id, cantidad, motivo, loteId }: {
      id: number; cantidad: number; motivo: string; loteId?: number | null
    }) => inventarioMedicinaService.registrarBaja(id, cantidad, motivo, loteId),
    onSuccess: () => {
      notifications.show({
        title:   'Existencias dadas de baja',
        message: 'Las unidades salieron del inventario y quedó constancia en el kardex.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
      // Lo que se retiró ya no debe ofrecerse al recetar.
      qc.invalidateQueries({ queryKey: ['medicinas-buscar'] })
    },
    onError,
  })

  const ajustarInventario = useMutation({
    mutationFn: ({ id, nuevoStock, motivo }: {
      id: number; nuevoStock: number; motivo: string
    }) => inventarioMedicinaService.ajustarInventario(
      id, nuevoStock, motivo
    ),
    onSuccess: () => {
      notifications.show({
        title:   'Inventario ajustado',
        message: 'El stock fue corregido correctamente.',
        color:   'blue',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })


  const toggleEstado = useMutation({
    mutationFn: (id: number) =>
      inventarioMedicinaService.toggleEstado(id),
    onSuccess: (data) => {
      const reactivada = !!data?.estado
      notifications.show({
        title:   reactivada
          ? 'Medicina reactivada'
          : 'Medicina retirada del catálogo',
        message: reactivada
          ? 'Vuelve a estar disponible para recetar y despachar.'
          : 'Deja de aparecer en recetas y despachos. Sus existencias no se movieron.',
        color:   reactivada ? 'emerald' : 'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
      // Deja de estar disponible —o vuelve a estarlo— para recetar.
      qc.invalidateQueries({ queryKey: ['medicinas-buscar'] })
    },
    onError,
  })

  return { crear, actualizar, registrarBaja, ajustarInventario, toggleEstado }
}
