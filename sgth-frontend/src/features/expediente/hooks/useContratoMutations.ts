import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import {
  actividadLaboralService, type ReprogramarPlazoData,
} from '../services/actividadLaboralService'
import { getApiErrorMessage } from '@/types/api'

export function useContratoMutations(servidorId: number) {
  const qc = useQueryClient()

  const reprogramarPlazo = useMutation({
    mutationFn: ({ contratoId, ...datos }: { contratoId: number } & ReprogramarPlazoData) =>
      actividadLaboralService.reprogramarPlazo(servidorId, contratoId, datos),
    onSuccess: () => {
      notifications.show({
        title: 'Plazo reprogramado',
        message: 'La nueva fecha de vencimiento quedó registrada con su motivo.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['actividad-laboral'] })
      // El vencimiento decide cuándo cesa el servidor: cambiarlo altera lo que
      // muestran su ficha y el listado.
      qc.invalidateQueries({ queryKey: ['servidor'] })
      qc.invalidateQueries({ queryKey: ['servidores'] })
      qc.invalidateQueries({ queryKey: ['contratos'] })
    },
    onError: (error) => {
      notifications.show({
        title: 'No se pudo reprogramar',
        message: getApiErrorMessage(error, 'No se pudo cambiar el plazo del contrato.'),
        color: 'red',
        icon: React.createElement(IconX, { size: 16 }),
      })
    },
  })

  return { reprogramarPlazo }
}
