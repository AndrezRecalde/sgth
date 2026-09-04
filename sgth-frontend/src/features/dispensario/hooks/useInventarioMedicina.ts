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

export function useKardexMedicina(id: number | null) {
  return useQuery({
    queryKey: ['inventario-medicinas', 'kardex', id],
    queryFn:  () => inventarioMedicinaService.kardex(id!),
    enabled:  !!id,
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
      const estado = data?.estado ? 'reactivada' : 'dada de baja'
      notifications.show({
        title:   `Medicina ${estado}`,
        message: `La medicina fue ${estado} correctamente.`,
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, actualizar, ajustarInventario, toggleEstado }
}
