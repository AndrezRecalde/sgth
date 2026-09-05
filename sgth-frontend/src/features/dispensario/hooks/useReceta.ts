import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX, IconAlertTriangle } from '@tabler/icons-react'
import React from 'react'
import { recetaService } from '../services/recetaService'
import { getApiErrorMessage } from '@/types/api'
import type { EmitirRecetaData } from '../services/recetaService'

export function useEmitirReceta(consultaId?: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: EmitirRecetaData) =>
      recetaService.emitir(data),
    onSuccess: (result) => {
      if (result?.alertas_alergias?.length) {
        result.alertas_alergias.forEach(alerta =>
          notifications.show({
            title:   'Alerta de alergia',
            message: alerta,
            color:   'orange',
            icon:    React.createElement(
              IconAlertTriangle, { size: 16 }
            ),
            autoClose: false,
          })
        )
      }
      notifications.show({
        title:   'Receta emitida',
        message: 'La receta médica fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['consultas'] })
      if (consultaId) {
        qc.invalidateQueries({
          queryKey: ['recetas', 'consulta', consultaId],
        })
      }
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}

export function useAccionesItem(consultaId: number) {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({
      queryKey: ['recetas', 'consulta', consultaId],
    })

  const onError = (error: unknown) =>
    notifications.show({
      title:   'Error',
      message: getApiErrorMessage(error),
      color:   'red',
      icon:    React.createElement(IconX, { size: 16 }),
    })

  const actualizarItem = useMutation({
    mutationFn: ({ recetaId, itemId, data }: {
      recetaId: number
      itemId:   number
      data: {
        cantidad_prescrita: number
        dosis:       string
        frecuencia:  string
        duracion:    string
        observaciones?: string | null
      }
    }) => recetaService.actualizarItem(recetaId, itemId, data),
    onSuccess: () => {
      notifications.show({
        title:   'Ítem actualizado',
        message: 'El medicamento fue actualizado.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const quitarItem = useMutation({
    mutationFn: ({ recetaId, itemId }: {
      recetaId: number; itemId: number
    }) => recetaService.quitarItem(recetaId, itemId),
    onSuccess: () => {
      notifications.show({
        title:   'Ítem eliminado',
        message: 'El medicamento fue removido de la receta.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { actualizarItem, quitarItem }
}

export function useRecetasFarmacia(params?: {
  fecha_desde?: string
  fecha_hasta?: string
  medico_id?:   number
  estado?:      string
  page?:        number
  per_page?:    number
}) {
  return useQuery({
    queryKey: ['recetas', 'farmacia', params],
    queryFn:  () => recetaService.listarFarmacia(params),
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
    // La página anterior se queda a la vista mientras llega la siguiente; sin
    // esto la tabla parpadea a vacío en cada salto de página.
    placeholderData: (anterior) => anterior,
  })
}

export function useAnularReceta() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      recetaService.anular(id, motivo),
    onSuccess: () => {
      notifications.show({
        title:   'Receta anulada',
        message: 'La receta ya no aparecerá pendiente de entrega.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      // Anular no mueve stock: lo entregado ya salió y su egreso sigue en pie.
      qc.invalidateQueries({ queryKey: ['recetas'] })
      qc.invalidateQueries({ queryKey: ['consultas'] })
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'No se pudo anular',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}

export function useDespacharReceta() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: {
      id:   number
      data: import('../services/recetaService').DespacharRecetaData
    }) => recetaService.despachar(id, data),
    onSuccess: () => {
      notifications.show({
        title:   'Receta despachada',
        message: 'Los medicamentos fueron despachados correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['recetas'] })
      // Despachar descuenta existencias: el listado de Farmacia, el kardex y
      // el contador de stock bajo del menú cuelgan de esta clave y quedaban
      // mostrando el stock de antes de la entrega.
      qc.invalidateQueries({ queryKey: ['inventario-medicinas'] })
      // Y lo que se agotó al despachar ya no debe ofrecerse al recetar.
      qc.invalidateQueries({ queryKey: ['medicinas-buscar'] })
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error al despachar',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}
