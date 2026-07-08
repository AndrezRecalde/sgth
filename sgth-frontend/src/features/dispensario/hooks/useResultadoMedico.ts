import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { resultadoMedicoService } from '../services/resultadoMedicoService'
import { getApiErrorMessage } from '@/types/api'

export function useResultadosPorConsulta(
  historiaClinicaId: number,
  consultaId: number
) {
  return useQuery({
    queryKey: ['resultados', 'consulta', consultaId],
    queryFn:  () => resultadoMedicoService.listar({
      historia_clinica_id: historiaClinicaId,
      consulta_medica_id:  consultaId,
    }),
    enabled:  !!historiaClinicaId && !!consultaId,
    staleTime: 1000 * 60,
  })
}

export function useResultadosPorHistoria(historiaClinicaId: number) {
  return useQuery({
    queryKey: ['resultados', 'historia', historiaClinicaId],
    queryFn:  () => resultadoMedicoService.listar({
      historia_clinica_id: historiaClinicaId,
    }),
    enabled:  !!historiaClinicaId,
    staleTime: 1000 * 60,
  })
}

export function useSubirResultado(consultaId: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (formData: FormData) =>
      resultadoMedicoService.subir(formData),
    onSuccess: () => {
      notifications.show({
        title:   'Resultado subido',
        message: 'El archivo fue registrado correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['resultados', 'consulta', consultaId],
      })
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

export function useEliminarResultado(consultaId: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) =>
      resultadoMedicoService.eliminar(id),
    onSuccess: () => {
      notifications.show({
        title:   'Resultado eliminado',
        message: 'El archivo fue removido correctamente.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['resultados', 'consulta', consultaId],
      })
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
