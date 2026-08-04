import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import {
  expedienteService,
  type ActualizarBorradorData,
  type TransicionarData,
} from '../services/expedienteService'
import { getApiErrorMessage } from '@/types/api'

export function useMovimientoMutations(servidorId?: number | null) {
  const qc = useQueryClient()

  const actualizarBorrador = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ActualizarBorradorData }) =>
      expedienteService.actualizarBorradorMovimiento(id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Borrador actualizado',
        message: 'Los cambios quedaron guardados en la acción de personal.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['movimientos', servidorId ?? undefined] })
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      // El detalle abierto en el drawer vive bajo otra clave.
      qc.invalidateQueries({ queryKey: ['movimiento'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
    },
    onError: (error) => {
      notifications.show({
        title: 'No se pudo guardar',
        message: getApiErrorMessage(error, 'No se pudo actualizar el borrador.'),
        color: 'red',
        icon: React.createElement(IconX, { size: 16 }),
      })
    },
  })

  const transicionar = useMutation({
    mutationFn: ({ id, ...datos }: { id: number } & TransicionarData) =>
      expedienteService.transicionarMovimiento(id, datos),
    onSuccess: () => {
      notifications.show({
        title: 'Acción actualizada',
        message: 'La acción de personal avanzó de estado.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      qc.invalidateQueries({ queryKey: ['movimiento'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
      // Registrar una acción materializa o cierra el vínculo laboral.
      qc.invalidateQueries({ queryKey: ['servidores'] })
      qc.invalidateQueries({ queryKey: ['contratos'] })
      qc.invalidateQueries({ queryKey: ['actividad-laboral'] })
    },
    onError: (error) => {
      notifications.show({
        title: 'No se pudo avanzar',
        message: getApiErrorMessage(error, 'No se pudo cambiar el estado de la acción.'),
        color: 'red',
        icon: React.createElement(IconX, { size: 16 }),
      })
    },
  })

  return { actualizarBorrador, transicionar }
}

export function useMovimiento(id: number | null) {
  return useQuery({
    queryKey: ['movimiento', id],
    queryFn: () => expedienteService.obtenerMovimiento(id!),
    enabled: id !== null,
    staleTime: 1000 * 30,
  })
}

export function useBandejaMovimientos(params?: {
  estado?: string
  tipo_movimiento?: string
  anio?: number
}) {
  return useQuery({
    queryKey: ['bandeja-movimientos', params],
    queryFn: () => expedienteService.listarBandejaMovimientos(params),
    staleTime: 1000 * 60,
  })
}
