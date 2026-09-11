'use client'

import { Badge, Stack, Text } from '@mantine/core'
import { IconPrinter } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ESTADO_LABELS, TIPO_LABELS, TONO_ESTADO } from './permisos.constants'
import { diasParaVencer, duracion, fechaPermiso } from '../utils/horarioPermiso'
import type { DataTableColumn } from 'mantine-datatable'
import type { PermisoServidor } from '@/types/api'

interface Acciones {
  exportandoId: number | null
  onExportar:   (id: number) => void
}

function PlazoRespaldo({ dias }: { dias: number }) {
  const texto = dias <= 0
    ? 'Plazo del respaldo vencido'
    : dias === 1
      ? 'Entrega el respaldo hoy'
      : `Entrega el respaldo en ${dias} días`

  return (
    <Text size="xs" c={SEMANTIC_COLOR[dias <= 1 ? 'danger' : 'warning']}>
      {texto}
    </Text>
  )
}

/**
 * Las columnas de «Mis permisos».
 *
 * Sin la columna del servidor —son todos del mismo— y con el motivo, que el
 * titular siempre puede leer. Solo se ofrece imprimir: confirmar, rechazar o
 * revertir son trabajo de Recepción y de Talento Humano.
 */
export function getMisPermisosColumns(acciones: Acciones): DataTableColumn<PermisoServidor>[] {
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
      accessor: 'tipo',
      title: 'Tipo',
      width: 130,
      render: ({ tipo }) => (
        <Badge size="sm" variant="light" color={SEMANTIC_COLOR.info}>
          {TIPO_LABELS[tipo] ?? tipo}
        </Badge>
      ),
    },
    {
      accessor: 'fecha',
      title: 'Fecha',
      width: 110,
      render: ({ fecha }) => <Text size="sm">{fecha ? fechaPermiso(fecha) : '—'}</Text>,
    },
    {
      accessor: 'hora_inicio',
      title: 'Horario',
      width: 140,
      render: ({ hora_inicio, hora_fin }) => (
        <Stack gap={2}>
          <Text size="sm" ff="monospace">
            {hora_inicio.substring(0, 5)} — {hora_fin.substring(0, 5)}
          </Text>
          <Badge size="xs" color={SEMANTIC_COLOR.info} variant="light">
            {duracion(hora_inicio, hora_fin)}
          </Badge>
        </Stack>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 180,
      render: ({ estado, vence_en }) => (
        <Stack gap={2}>
          <StatusBadge tone={TONO_ESTADO[estado] ?? 'neutral'}>
            {ESTADO_LABELS[estado] ?? estado}
          </StatusBadge>
          {/*
            Lo que más le importa al servidor de un pendiente: cuánto le queda
            para entregar el respaldo antes de que pase a falta injustificada.
            Va debajo del estado y no en una columna propia, que en los demás
            estados quedaba vacía y le quitaba al motivo el ancho que necesita.
          */}
          {estado === 'pendiente' && vence_en && (
            <PlazoRespaldo dias={diasParaVencer(vence_en)} />
          )}
        </Stack>
      ),
    },
    {
      accessor: 'observacion',
      title: 'Motivo',
      render: ({ observacion }) =>
        observacion ? (
          <Text size="sm" lineClamp={2} title={observacion}>{observacion}</Text>
        ) : (
          <Text size="sm" c="dimmed">—</Text>
        ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (p) => (
        <TableActions
          actions={[
            {
              label: acciones.exportandoId === p.id ? 'Exportando...' : 'Imprimir permiso',
              icon: <IconPrinter size={14} />,
              color: 'blue',
              onClick: () => acciones.onExportar(p.id),
            },
          ]}
        />
      ),
    },
  ]
}
