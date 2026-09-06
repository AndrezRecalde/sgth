'use client'

import { Badge, Stack, Text } from '@mantine/core'
import {
  IconArrowBackUp, IconCheck, IconPrinter, IconShieldCheck, IconX,
} from '@tabler/icons-react'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import {
  ESTADOS_CONFIRMADOS, ESTADO_LABELS, TIPO_LABELS, TONO_ESTADO,
} from './permisos.constants'
import type { DataTableColumn } from 'mantine-datatable'
import type { PermisoServidor } from '@/types/api'

interface ColumnActions {
  exportandoId: number | null
  onExportar:   (id: number) => void
  onConfirmar:  (folio: string) => void
  onValidarTs:  (id: number) => void
  onAnular:     (id: number) => void
  onRechazar:   (p: PermisoServidor) => void
  onRevertir:   (p: PermisoServidor) => void
}

function duracion(horaInicio: string, horaFin: string): string {
  const [hI, mI] = horaInicio.substring(0, 5).split(':').map(Number)
  const [hF, mF] = horaFin.substring(0, 5).split(':').map(Number)
  const minutos = hF * 60 + mF - (hI * 60 + mI)
  const horas = Math.floor(minutos / 60)
  const mins = minutos % 60

  return horas > 0 ? `${horas}h${mins > 0 ? ` ${mins}m` : ''}` : `${mins}m`
}

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
      width: 100,
      render: ({ tipo }) => (
        <Badge size="sm" variant="light" color="blue">
          {TIPO_LABELS[tipo as string] ?? tipo}
        </Badge>
      ),
    },
    {
      accessor: 'fecha',
      title: 'Fecha',
      width: 110,
      render: ({ fecha }) => (
        <Text size="sm">
          {fecha
            ? new Date(fecha).toLocaleDateString('es-EC', {
                timeZone: 'UTC', day: '2-digit', month: '2-digit', year: 'numeric',
              })
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
            <Badge size="xs" color="blue" variant="light">
              {duracion(hora_inicio, hora_fin)}
            </Badge>
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

        const dias = Math.ceil(
          (new Date(vence_en).getTime() - Date.now()) / 86_400_000
        )

        return (
          <Badge size="sm" variant="light" color={dias <= 1 ? 'red' : 'amber'}>
            {dias <= 0 ? 'Vencido' : dias === 1 ? 'Hoy' : `${dias} días`}
          </Badge>
        )
      },
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 140,
      render: ({ estado }) => (
        <StatusBadge tone={TONO_ESTADO[estado as string] ?? 'neutral'}>
          {ESTADO_LABELS[estado as string] ?? estado}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (p) => {
        const estado = p.estado as string
        const pendiente = estado === 'pendiente'

        return (
          <TableActions
            actions={[
              {
                label: actions.exportandoId === p.id ? 'Exportando...' : 'Imprimir permiso',
                icon: <IconPrinter size={14} />,
                color: 'blue',
                onClick: () => actions.onExportar(p.id),
              },
              {
                label: 'Confirmar recepción',
                icon: <IconCheck size={14} />,
                color: 'blue',
                onClick: () => p.folio && actions.onConfirmar(p.folio),
                hidden: !pendiente,
              },
              {
                label: 'Rechazar documento',
                icon: <IconX size={14} />,
                color: 'orange',
                onClick: () => actions.onRechazar(p),
                hidden: !pendiente,
              },
              {
                label: 'Validar Trabajo Social',
                icon: <IconShieldCheck size={14} />,
                color: 'emerald',
                onClick: () => actions.onValidarTs(p.id),
                hidden:
                  estado !== 'activo' ||
                  !['enfermedad', 'calamidad'].includes(p.tipo as string),
              },
              {
                label: 'Revertir confirmación',
                icon: <IconArrowBackUp size={14} />,
                color: 'orange',
                onClick: () => actions.onRevertir(p),
                hidden: !ESTADOS_CONFIRMADOS.includes(estado),
              },
              {
                label: 'Anular',
                icon: <IconX size={14} />,
                color: 'red',
                onClick: () =>
                  confirmar({
                    title: 'Anular permiso',
                    message: (
                      <>
                        Se anulará el permiso <b>{p.folio}</b> y dejará de contar
                        para el servidor.
                      </>
                    ),
                    destructiva: true,
                    confirmLabel: 'Anular',
                    onConfirm: () => actions.onAnular(p.id),
                  }),
                hidden: !pendiente,
              },
            ]}
          />
        )
      },
    },
  ]
}
