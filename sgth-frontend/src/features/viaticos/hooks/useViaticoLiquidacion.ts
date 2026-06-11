import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications }               from '@mantine/notifications'
import { IconCheck, IconX }            from '@tabler/icons-react'
import React                           from 'react'
import { viaticoService }              from '../services/viaticoService'
import { getApiErrorMessage }          from '@/types/api'

export function useViaticoLiquidacion() {
  const qc = useQueryClient()

  const onError = (error: unknown) =>
    notifications.show({
      title:   'Error',
      message: getApiErrorMessage(error),
      color:   'red',
      icon:    React.createElement(IconX, { size: 16 }),
    })

  const liquidar = useMutation({
    mutationFn: ({
      viaticoId, data,
    }: {
      viaticoId: number
      data:      Parameters<typeof viaticoService.liquidar>[1]
    }) => viaticoService.liquidar(viaticoId, data),
    onSuccess: (_data, { viaticoId }) => {
      notifications.show({
        title:   'Viático liquidado',
        message: 'La liquidación fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['viaticos'] })
      qc.invalidateQueries({ queryKey: ['viatico', viaticoId] })
      qc.invalidateQueries({ queryKey: ['viatico'] })
    },
    onError,
  })

  const guardarActividades = useMutation({
    mutationFn: ({
      viaticoId, actividades,
    }: {
      viaticoId:   number
      actividades: Parameters<
        typeof viaticoService.liquidacion.guardarActividades
      >[1]
    }) => viaticoService.liquidacion.guardarActividades(
      viaticoId, actividades
    ),
    onSuccess: (_data, { viaticoId }) => {
      notifications.show({
        title:   'Actividades guardadas',
        message: 'Las actividades se guardaron correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['liquidacion', viaticoId] })
      qc.invalidateQueries({ queryKey: ['viatico'] })
    },
    onError,
  })

  const guardarFacturas = useMutation({
    mutationFn: ({
      viaticoId, facturas,
    }: {
      viaticoId: number
      facturas:  Parameters<
        typeof viaticoService.liquidacion.guardarFacturas
      >[1]
    }) => viaticoService.liquidacion.guardarFacturas(
      viaticoId, facturas
    ),
    onSuccess: (_data, { viaticoId }) => {
      notifications.show({
        title:   'Facturas guardadas',
        message: 'Los comprobantes se guardaron correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['liquidacion', viaticoId] })
      qc.invalidateQueries({ queryKey: ['viatico'] })
    },
    onError,
  })

  const confirmarLiquidacion = useMutation({
    mutationFn: (viaticoId: number) =>
      viaticoService.liquidacion.confirmar(viaticoId),
    onSuccess: () => {
      notifications.show({
        title:   'Liquidación registrada',
        message: 'La liquidación fue confirmada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['viaticos'] })
      qc.invalidateQueries({ queryKey: ['viatico'] })
      qc.invalidateQueries({ queryKey: ['liquidacion'] })
    },
    onError,
  })

  return {
    liquidar,
    guardarActividades,
    guardarFacturas,
    confirmarLiquidacion,
  }
}
