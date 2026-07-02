import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX, IconAlertTriangle } from '@tabler/icons-react'
import React from 'react'
import { recetaService } from '../services/recetaService'
import { getApiErrorMessage } from '@/types/api'
import type { EmitirRecetaData } from '../services/recetaService'

export function useEmitirReceta() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: EmitirRecetaData) =>
      recetaService.emitir(data),
    onSuccess: (result) => {
      if (result?.alertas_alergias?.length) {
        result.alertas_alergias.forEach(alerta =>
          notifications.show({
            title:   '⚠ Alerta de alergia',
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
