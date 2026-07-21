import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'
import type { EppEntrega } from '../services/ssoService'

interface Params {
  page?: number
  servidor_id?: number
  equipo_proteccion_id?: number
  fecha_inicio?: string
  fecha_fin?: string
}

export function useEppEntregas(params?: Params) {
  return useQuery({
    queryKey: ['sso-epp-entregas', params],
    queryFn: () => ssoService.listarEntregasEpp(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useReporteEppEntregas(params: { fecha_inicio: string; fecha_fin: string; puesto_id?: number } | null) {
  return useQuery({
    queryKey: ['sso-epp-reporte', params],
    queryFn: () => ssoService.reporteEntregasEpp(params!),
    enabled: !!params,
    staleTime: 1000 * 60,
  })
}

export function useKitEppServidor(servidorId: number | null) {
  return useQuery({
    queryKey: ['sso-epp-kit-servidor', servidorId],
    queryFn: () => ssoService.obtenerKitEppServidor(servidorId!),
    enabled: !!servidorId,
    staleTime: 1000 * 30,
  })
}

export function useEppEntregaMutations() {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-epp-entregas'] })

  const onError = (error: unknown) =>
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const registrar = useMutation({
    mutationFn: (data: Partial<EppEntrega>) => ssoService.registrarEntregaEpp(data),
    onSuccess: () => {
      notifications.show({
        title: 'Entrega registrada',
        message: 'El movimiento de EPP fue registrado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const registrarKit = useMutation({
    mutationFn: (data: {
      servidor_id: number
      fecha_entrega: string
      observaciones?: string
      equipos: { equipo_proteccion_id: number; cantidad?: number }[]
    }) => ssoService.registrarEntregaKitEpp(data),
    onSuccess: (entregas) => {
      notifications.show({
        title: 'Kit entregado',
        message: `Se registraron ${entregas.length} equipos de protección.`,
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { registrar, registrarKit }
}
