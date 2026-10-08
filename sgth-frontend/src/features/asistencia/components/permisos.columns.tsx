'use client'

import { Stack, Text } from '@mantine/core'
import { StatusBadge, TableActions } from '@/components/ui'
import { ESTADO_LABELS, TIPO_LABELS, TONO_ESTADO } from './permisos.constants'
import type { DataTableColumn } from 'mantine-datatable'
import type { PermisoServidor } from '@/types/api'
import { accionesDelPermiso, type ColumnActions } from './permisos.acciones'
import { diasParaVencer, duracion } from '../utils/horarioPermiso'
import { formatFecha } from '@/lib/fecha'
import { MotivoDeEstadoTexto } from './MotivoDeEstadoTexto'
import { EnSirha7Texto } from './EnSirha7Texto'

export function getPermisosColumns(
  actions: ColumnActions
): DataTableColumn<PermisoServidor>[] {
  return [
    {
      accessor: 'folio',
      title: 'Folio',
      width: 145,
      render: ({ folio }) => (
        <Text size="sm" ff="monospace" fw={500}>{folio ?? '—'}</Text>
      ),
    },
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (p) => {
        const s = p.servidor
        if (!s) return <Text size="sm" c="dimmed">—</Text>

        return <Text size="sm">{[s.apellido, s.nombre].filter(Boolean).join(' ')}</Text>
      },
    },
    {
      accessor: 'tipo',
      title: 'Tipo',
      // «CALAMIDAD DOMÉSTICA» mide 147 px (medido en el navegador) y la celda
      // lleva 32 de relleno. Con las etiquetas cortas de antes bastaban 130.
      width: 185,
      render: ({ tipo }) => (
        <StatusBadge>
          {TIPO_LABELS[tipo as string] ?? tipo}
        </StatusBadge>
      ),
    },
    {
      accessor: 'fecha',
      title: 'Fecha',
      width: 110,
      render: ({ fecha }) => (
        <Text size="sm">
          {fecha
            ? formatFecha(fecha)
            : '—'}
        </Text>
      ),
    },
    {
      accessor: 'hora_inicio',
      title: 'Horario / Tiempo',
      width: 140,
      render: ({ hora_inicio, hora_fin }) => {
        if (!hora_inicio || !hora_fin) return <Text size="sm" c="dimmed">—</Text>

        return (
          <Stack gap={2}>
            <Text size="sm" ff="monospace">
              {hora_inicio.substring(0, 5)} — {hora_fin.substring(0, 5)}
            </Text>
            <StatusBadge size="xs">
              {duracion(hora_inicio, hora_fin)}
            </StatusBadge>
          </Stack>
        )
      },
    },
    {
      // El plazo de 72 horas laborables no se veía en ningún lado: nadie sabía
      // a cuánto estaba un permiso de convertirse en falta injustificada.
      accessor: 'vence_en',
      title: 'Vence',
      width: 110,
      render: ({ estado, vence_en }) => {
        if (estado !== 'pendiente' || !vence_en) {
          return <Text size="sm" c="dimmed">—</Text>
        }

        const dias = diasParaVencer(vence_en)

        return (
          <StatusBadge tone={dias <= 1 ? 'danger' : 'warning'}>
            {dias <= 0 ? 'Vencido' : dias === 1 ? 'Hoy' : `${dias} días`}
          </StatusBadge>
        )
      },
    },
    {
      accessor: 'estado',
      title: 'Estado',
      // «FALTA INJUSTIFICADA» mide 133 px: con 140 de celda y 32 de relleno
      // desbordaba 25.
      width: 180,
      render: (p) => (
        <Stack gap={2}>
          <StatusBadge tone={TONO_ESTADO[p.estado as string] ?? 'neutral'}>
            {ESTADO_LABELS[p.estado as string] ?? p.estado}
          </StatusBadge>
          <MotivoDeEstadoTexto permiso={p} />
          <EnSirha7Texto permiso={p} />
        </Stack>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (p) => <TableActions actions={accionesDelPermiso(p, actions)} />,
    },
  ]
}
