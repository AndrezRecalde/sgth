import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { convocatoriaService } from '../services/convocatoriaService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearConvocatoriaData } from '../services/convocatoriaService'

export function useConvocatorias(params?: Record<string, unknown>) {
  return useQuery({
    queryKey: ['convocatorias', params],
    queryFn:  () => convocatoriaService.listar(params),
    staleTime: 1000 * 60,
  })
}

export function useConvocatoriaDetalle(id: number | null) {
  return useQuery({
    queryKey: ['convocatoria', id],
    queryFn:  () => convocatoriaService.obtener(id!),
    enabled:  !!id,
    staleTime: 1000 * 60,
  })
}

export function useCrearConvocatoria() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: CrearConvocatoriaData) =>
      convocatoriaService.crear(data),
    onSuccess: () => {
      notifications.show({
        title:   'Convocatoria creada',
        message: 'La convocatoria fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['convocatorias'] })
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

export function useActualizarConvocatoria() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: {
      id:   number
      data: Partial<CrearConvocatoriaData & { estado: string }>
    }) => convocatoriaService.actualizar(id, data),
    onSuccess: (_, { id }) => {
      notifications.show({
        title:   'Convocatoria actualizada',
        message: 'Los cambios fueron guardados.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['convocatorias'] })
      qc.invalidateQueries({ queryKey: ['convocatoria', id] })
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

export function usePublicarConvocatoria() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => convocatoriaService.publicar(id),
    onSuccess: (_, id) => {
      notifications.show({
        title:   'Convocatoria publicada',
        message: 'La convocatoria ya es visible para los postulantes.',
        color:   'blue',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['convocatorias'] })
      qc.invalidateQueries({ queryKey: ['convocatoria', id] })
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

export function useEliminarConvocatoria() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => convocatoriaService.eliminar(id),
    onSuccess: () => {
      notifications.show({
        title:   'Convocatoria eliminada',
        message: 'La convocatoria fue eliminada.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['convocatorias'] })
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

export function usePostulantes(convocatoriaId: number | null) {
  return useQuery({
    queryKey: ['postulantes', convocatoriaId],
    queryFn:  () => convocatoriaService.listarPostulantes(convocatoriaId!),
    enabled:  !!convocatoriaId,
    staleTime: 1000 * 30,
  })
}

export function useInscribirPostulante(convocatoriaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: Parameters<typeof convocatoriaService.inscribirPostulante>[1]) =>
      convocatoriaService.inscribirPostulante(convocatoriaId, data),
    onSuccess: () => {
      notifications.show({
        title:   'Postulante inscrito',
        message: 'El postulante fue inscrito correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['postulantes', convocatoriaId] })
      // El aspirante aparece también en Reclutamiento Express, bajo otras
      // claves: sin esto los conteos del contenedor y el filtro de años se
      // quedaban viejos hasta recargar la página.
      qc.invalidateQueries({ queryKey: ['express-aspirantes'] })
      qc.invalidateQueries({ queryKey: ['express-resumen'] })
      qc.invalidateQueries({ queryKey: ['express-anios'] })
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

export function useCalificarPostulante(convocatoriaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ postulanteId, data }: {
      postulanteId: number
      data: {
        puntaje_meritos:   number
        puntaje_oposicion: number
        observaciones?:    string | null
      }
    }) =>
      api.post<ApiResponse<unknown>>(
        `/seleccion/postulantes/${postulanteId}/calificar`,
        data
      ).then(r => r.data.datos),
    onSuccess: () => {
      notifications.show({
        title:   'Calificación registrada',
        message: 'El puntaje del candidato fue guardado.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['postulantes', convocatoriaId] })
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

/**
 * Despacha uno o varios candidatos al dispensario. El backend acota la
 * cantidad a las vacantes de la convocatoria; en los contenedores express no
 * hay tope porque los aspirantes no compiten entre sí.
 */
export function useEnviarAlDispensario(convocatoriaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (postulanteIds: number | number[]) =>
      api.post<ApiResponse<unknown>>(
        `/seleccion/convocatorias/${convocatoriaId}/declarar-ganador`,
        {
          postulante_ganador_ids: Array.isArray(postulanteIds)
            ? postulanteIds
            : [postulanteIds],
        }
      ).then(r => r.data),
    onSuccess: (_data, variables) => {
      const cantidad = Array.isArray(variables) ? variables.length : 1

      notifications.show({
        title:   'Enviado al Dispensario',
        message: cantidad === 1
          ? 'El candidato fue enviado al Dispensario Médico para evaluación. El ganador será confirmado tras el dictamen médico.'
          : `${cantidad} candidatos fueron enviados al Dispensario Médico. Cada uno se confirma tras su propio dictamen.`,
        color:   'blue',
        icon:    React.createElement(IconCheck, { size: 16 }),
        autoClose: 8000,
      })
      qc.invalidateQueries({ queryKey: ['convocatoria', convocatoriaId] })
      qc.invalidateQueries({ queryKey: ['postulantes', convocatoriaId] })
      // Los mismos postulantes se listan en Reclutamiento Express bajo otra
      // clave; sin esto la tabla y los conteos del contenedor quedan viejos.
      qc.invalidateQueries({ queryKey: ['express-aspirantes'] })
      qc.invalidateQueries({ queryKey: ['express-resumen'] })
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

export function useConfirmarGanador(convocatoriaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () =>
      convocatoriaService.confirmarGanador(convocatoriaId),
    onSuccess: () => {
      notifications.show({
        title:   'Ganador confirmado',
        message: 'El candidato fue declarado ganador oficial. La convocatoria ha sido finalizada.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
        autoClose: 6000,
      })
      qc.invalidateQueries({ queryKey: ['convocatoria', convocatoriaId] })
      qc.invalidateQueries({ queryKey: ['postulantes', convocatoriaId] })
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
