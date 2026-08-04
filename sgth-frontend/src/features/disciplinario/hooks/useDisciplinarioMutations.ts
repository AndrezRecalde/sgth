import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import {
  disciplinarioService,
  type AvanzarSumarioData,
  type TransicionarVistoBuenoData,
} from '../services/disciplinarioService'
import type { ApiResponse, SumarioFormData, VistoBuenoFormData } from '@/types/api'

function onError(error: AxiosError<ApiResponse>) {
  notifications.show({
    title: 'No se pudo completar la acción',
    message: error.response?.data?.mensaje ?? 'Error inesperado',
    color: 'red',
    icon: React.createElement(IconX, { size: 16 }),
  })
}

function exito(title: string, message: string) {
  notifications.show({
    title,
    message,
    color: 'emerald',
    icon: React.createElement(IconCheck, { size: 16 }),
  })
}

export function useDisciplinarioMutations() {
  const qc = useQueryClient()

  const invalidarSumarios = () => {
    qc.invalidateQueries({ queryKey: ['sumarios'] })
    qc.invalidateQueries({ queryKey: ['movimientos'] })
  }

  const invalidarVistosBuenos = () => {
    qc.invalidateQueries({ queryKey: ['vistos-buenos'] })
    qc.invalidateQueries({ queryKey: ['movimientos'] })
  }

  const crearSumario = useMutation({
    mutationFn: (data: SumarioFormData) => disciplinarioService.crearSumario(data),
    onSuccess: () => {
      exito('Sumario abierto', 'El sumario administrativo fue registrado.')
      invalidarSumarios()
    },
    onError,
  })

  const avanzarSumario = useMutation({
    mutationFn: ({ id, data }: { id: number; data: AvanzarSumarioData }) =>
      disciplinarioService.avanzarSumario(id, data),
    onSuccess: () => {
      exito('Sumario actualizado', 'Se registró el avance procesal.')
      invalidarSumarios()
    },
    onError,
  })

  const crearVistoBueno = useMutation({
    mutationFn: (data: VistoBuenoFormData) => disciplinarioService.crearVistoBueno(data),
    onSuccess: () => {
      exito('Visto bueno solicitado', 'El trámite quedó registrado.')
      invalidarVistosBuenos()
    },
    onError,
  })

  const transicionarVistoBueno = useMutation({
    mutationFn: ({ id, data }: { id: number; data: TransicionarVistoBuenoData }) =>
      disciplinarioService.transicionarVistoBueno(id, data),
    onSuccess: (_data, variables) => {
      exito(
        'Trámite actualizado',
        variables.data.estado === 'concedido'
          ? 'Se generó la Cesación de Funciones en borrador para revisión de Talento Humano.'
          : 'Se registró el avance del trámite.',
      )
      invalidarVistosBuenos()
    },
    onError,
  })

  return { crearSumario, avanzarSumario, crearVistoBueno, transicionarVistoBueno }
}
