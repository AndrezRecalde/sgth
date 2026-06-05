'use client'

import { Stack, Text, Badge, Group } from '@mantine/core'
import {
  IconCheck, IconX, IconAlertCircle,
} from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { useVuelosAutorizacion } from '../hooks/useViaticos'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { viaticoService } from '../services/viaticoService'
import { notifications } from '@mantine/notifications'
import { getApiErrorMessage } from '@/types/api'
import React from 'react'
import type { AutorizacionVuelo } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

export function VuelosTab() {
  const { data: vuelos = [], isLoading } = useVuelosAutorizacion()
  const qc = useQueryClient()

  const aprobar = useMutation({
    mutationFn: (id: number) =>
      viaticoService.vuelos.aprobar(id),
    onSuccess: () => {
      notifications.show({
        title:   'Vuelo aprobado',
        message: 'La autorización fue aprobada.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['vuelos-autorizacion'] })
    },
    onError: (error: unknown) => notifications.show({
      title:   'Error',
      message: getApiErrorMessage(error),
      color:   'red',
    }),
  })

  const rechazar = useMutation({
    mutationFn: (id: number) =>
      viaticoService.vuelos.rechazar(id, {
        observacion: 'Rechazado por el gestor',
      }),
    onSuccess: () => {
      notifications.show({
        title:   'Vuelo rechazado',
        message: 'La autorización fue rechazada.',
        color:   'orange',
        icon:    React.createElement(IconX, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['vuelos-autorizacion'] })
    },
    onError: (error: unknown) => notifications.show({
      title:   'Error',
      message: getApiErrorMessage(error),
      color:   'red',
    }),
  })

  const columns: DataTableColumn<AutorizacionVuelo>[] = [
    {
      accessor: 'id',
      title:    'ID',
      width:    60,
    },
    {
      accessor: 'justificacion',
      title:    'Justificación',
      render: ({ justificacion }) => (
        <Text size="sm" lineClamp={2}>
          {justificacion ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    120,
      render: ({ estado }) => {
        const colors: Record<string, string> = {
          pendiente: 'orange',
          aprobado:  'emerald',
          rechazado: 'red',
        }
        return (
          <Badge
            color={colors[estado as string] ?? 'gray'}
            variant="light"
            size="sm"
          >
            {estado as string}
          </Badge>
        )
      },
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (v) => (
        <TableActions actions={[
          {
            label:   'Aprobar',
            icon:    <IconCheck size={14} />,
            color:   'emerald',
            onClick: () => aprobar.mutate(Number(v.id)),
            hidden:  (v.estado as string) !== 'pendiente',
          },
          {
            label:   'Rechazar',
            icon:    <IconX size={14} />,
            color:   'red',
            onClick: () => rechazar.mutate(Number(v.id)),
            hidden:  (v.estado as string) !== 'pendiente',
          },
        ]} />
      ),
    },
  ]

  if (!isLoading && (vuelos as AutorizacionVuelo[]).length === 0) {
    return (
      <EmptyState
        icon={IconAlertCircle}
        title="Sin autorizaciones de vuelo pendientes"
        description="Las autorizaciones aparecen cuando se registra
                     un transporte aéreo en una solicitud."
      />
    )
  }

  return (
    <SgthTable
      records={vuelos as AutorizacionVuelo[]}
      columns={columns}
      fetching={isLoading}
      minHeight={200}
    />
  )
}
