import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { atencionEnfermeriaService } from '../services/atencionEnfermeriaService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearAtencionEnfermeriaData } from '../services/atencionEnfermeriaService'

export function useCatalogoServicios() {
  return useQuery({
    queryKey: ['catalogo-servicios-enfermeria'],
    queryFn:  atencionEnfermeriaService.catalogo,
    staleTime: 1000 * 60 * 30,
  })
}

export function useAtencionesEnfermeria(filtros?: {
  fecha?: string
  enfermera_id?: number
}) {
  return useQuery({
    queryKey: ['atenciones-enfermeria', filtros],
    queryFn:  () => atencionEnfermeriaService.listar(filtros),
    staleTime: 1000 * 15,
  })
}

export function useRegistrarAtencionEnfermeria() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearAtencionEnfermeriaData) =>
      atencionEnfermeriaService.crear(data),
    onSuccess: (data) => {
      notifications.show({
        title:   'Atención registrada',
        message: `Folio ${data.folio} registrado correctamente.`,
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['atenciones-enfermeria'] })
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
