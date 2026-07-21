import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { programaDrogasService } from '../services/programaDrogasService'
import { getApiErrorMessage } from '@/types/api'

export function useActividadesPrograma(params?: { fase?: string; solo_activas?: boolean }) {
  return useQuery({
    queryKey: ['sso-programa-drogas-actividades', params],
    queryFn: () => programaDrogasService.listarActividades(params),
    staleTime: 1000 * 30,
  })
}

export function useListaSeguimientoPrograma(periodo: string | null) {
  return useQuery({
    queryKey: ['sso-programa-drogas-seguimiento', periodo],
    queryFn: () => programaDrogasService.listaSeguimiento(periodo!),
    enabled: !!periodo,
    staleTime: 1000 * 30,
  })
}

export function useProgramaDrogasMutations() {
  const qc = useQueryClient()

  const onError = (error: unknown) =>
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const crearActividad = useMutation({
    mutationFn: (data: { fase: string; nombre: string; descripcion?: string }) =>
      programaDrogasService.crearActividad(data),
    onSuccess: () => {
      notifications.show({
        title: 'Actividad registrada',
        message: 'La actividad fue agregada al catálogo del programa.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-actividades'] })
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-seguimiento'] })
    },
    onError,
  })

  const eliminarActividad = useMutation({
    mutationFn: (id: number) => programaDrogasService.eliminarActividad(id),
    onSuccess: () => {
      notifications.show({
        title: 'Actividad eliminada',
        message: 'La actividad fue eliminada del catálogo.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-actividades'] })
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-seguimiento'] })
    },
    onError,
  })

  const registrarSeguimiento = useMutation({
    mutationFn: (data: {
      programa_droga_actividad_id: number
      periodo: string
      estado: string
      fecha_ejecucion?: string | null
      observaciones?: string
    }) => programaDrogasService.registrarSeguimiento(data),
    onSuccess: () => {
      notifications.show({
        title: 'Seguimiento registrado',
        message: 'El estado de la actividad fue actualizado.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['sso-programa-drogas-seguimiento'] })
    },
    onError,
  })

  return { crearActividad, eliminarActividad, registrarSeguimiento }
}
