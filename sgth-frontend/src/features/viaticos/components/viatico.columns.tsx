'use client'

import { Stack, Text } from '@mantine/core'
import { IconCurrencyDollar, IconEye } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFechaHora } from '@/lib/fecha'
import { ESTADO_LABELS, TONO_VIATICO, ZONA_LABELS } from '../constants/viatico.constants'
import { dolares } from '../utils/monto'
import type { AccionesViatico } from '../hooks/useAccionesViatico'
import type { EstadoViatico, ViaticoConRelaciones } from '@/types/api'

/*
| Las columnas que comparten «Mis viáticos» y la bandeja de Financiero.
|
| Antes cada tabla escribía las suyas: el código con otro ancho, el período
| con otro título («Período» y «Salida – regreso»), el monto alineado a la
| derecha en una y a la izquierda en la otra, y con o sin separador de miles.
*/

/** Lo mínimo que tienen las filas de las dos tablas. */
type FilaViatico = {
  codigo_viatico?: string | null
  estado?: string | null
  zona?: string | null
  datetime_salida?: string | null
  datetime_llegada?: string | null
  noches?: number | string | null
  monto_calculado?: number | string | null
  monto_anticipo?: number | string | null
}

export function columnaCodigo<T extends FilaViatico>(): DataTableColumn<T> {
  return {
    accessor: 'codigo_viatico',
    title:    'Código',
    width:    185,
    noWrap:   true,
    render:   (v) => <Text size="sm" fw={500}>{v.codigo_viatico ?? '—'}</Text>,
  }
}

export function columnaZona<T extends FilaViatico>(): DataTableColumn<T> {
  return {
    accessor: 'zona',
    title:    'Zona',
    // Sin ancho: es la que cede cuando la tabla no cabe, pero sin partirse
    // en tres líneas en un teléfono: ahí la tabla se desplaza de lado.
    noWrap:   true,
    render:   (v) => <Text size="sm">{ZONA_LABELS[v.zona ?? ''] ?? v.zona ?? '—'}</Text>,
  }
}

export function columnaPeriodo<T extends FilaViatico>(): DataTableColumn<T> {
  return {
    accessor: 'datetime_salida',
    title:    'Salida – regreso',
    width:    200,
    render:   (v) => {
      if (!v.datetime_salida) {
        return <StatusBadge tone="warning" variant="dot">Sin fechas</StatusBadge>
      }
      const noches = Number(v.noches ?? 0)
      return (
        <Stack gap={0}>
          <Text size="sm" style={{ whiteSpace: 'nowrap' }}>
            {formatFechaHora(v.datetime_salida, { conHora: false })} – {formatFechaHora(v.datetime_llegada, { conHora: false })}
          </Text>
          <Text size="xs" c="dimmed">
            {noches} {noches === 1 ? 'noche' : 'noches'}
          </Text>
        </Stack>
      )
    },
  }
}

export function columnaMonto<T extends FilaViatico>(): DataTableColumn<T> {
  return {
    accessor:  'monto_calculado',
    title:     'Monto',
    width:     120,
    textAlign: 'right',
    render:    (v) => (
      <Stack gap={0} align="flex-end">
        <Text size="sm">{dolares(v.monto_calculado)}</Text>
        {Number(v.monto_anticipo ?? 0) > 0 && (
          <Text size="xs" c="dimmed">Anticipo {dolares(v.monto_anticipo)}</Text>
        )}
      </Stack>
    ),
  }
}

export function columnaEstado<T extends FilaViatico>(): DataTableColumn<T> {
  return {
    accessor: 'estado',
    title:    'Estado',
    width:    180,
    render:   (v) => (
      <StatusBadge tone={TONO_VIATICO[v.estado ?? ''] ?? 'neutral'}>
        {ESTADO_LABELS[v.estado as EstadoViatico] ?? v.estado}
      </StatusBadge>
    ),
  }
}

interface AccionesMisViaticos {
  onVer: (v: ViaticoConRelaciones) => void
  puede: AccionesViatico
}

/**
 * «Mis viáticos»: sin columna de servidor, porque todos son de quien mira.
 *
 * Sin «Aprobar»: nadie aprueba un viático en el que viaja. Se aprueba desde
 * la ficha, que además pide el coeficiente cuando el viaje es al exterior.
 */
export function getViaticoColumns({
  onVer,
  puede,
}: AccionesMisViaticos): DataTableColumn<ViaticoConRelaciones>[] {
  return [
    columnaCodigo(),
    columnaZona(),
    columnaPeriodo(),
    columnaMonto(),
    columnaEstado(),
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render:   (v) => (
        <TableActions
          actions={[
            { label: 'Abrir viático', icon: <IconEye size={14} />, onClick: () => onVer(v) },
            {
              label:   'Presentar la liquidación',
              icon:    <IconCurrencyDollar size={14} />,
              onClick: () => onVer(v),
              hidden:  !puede.liquidar(v),
            },
          ]}
        />
      ),
    },
  ]
}
